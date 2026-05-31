<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class GeminiVoiceLedgerParseService
{
    public function __construct(
        private ?string $apiKey,
        private string $preferredModel,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            config('services.gemini.api_key'),
            (string) config('services.gemini.model', 'gemini-2.5-flash-lite'),
        );
    }

    /**
     * @param  list<string>  $quickItems
     * @return array{
     *     type: ?string,
     *     amount_sen: ?int,
     *     note: ?string,
     *     next_due_at: ?string,
     *     item_key: ?string,
     *     confidence: ?string,
     *     summary: ?string,
     *     error: ?string
     * }
     */
    public function parseFromTranscript(
        string $transcript,
        string $intentHint,
        string $currencyCode,
        string $customerName,
        array $quickItems,
        string $appLanguage,
    ): array {
        $key = trim((string) $this->apiKey);
        if ($key === '') {
            return $this->emptyResult('gemini_not_configured');
        }

        $transcript = trim($transcript);
        if ($transcript === '') {
            return $this->emptyResult('empty_transcript');
        }

        $today = now()->format('Y-m-d');
        $quickJson = json_encode(array_values($quickItems), JSON_UNESCAPED_UNICODE) ?: '[]';
        $summaryLang = str_starts_with(strtolower($appLanguage), 'ms') ? 'Malay (Bahasa Malaysia)' : 'English';

        $prompt = <<<PROMPT
You parse spoken shop-ledger commands for a small retail app (udhaar / credit and customer payments).
The shopkeeper spoke in any language (Urdu, Pashto, Malay, English, Roman Urdu, etc.). The transcript may be imperfect STT text.

Context:
- Today's date: {$today}
- Ledger currency code: {$currencyCode}
- Customer name: {$customerName}
- User opened the sheet for intent_hint: "{$intentHint}" (credit = gave goods on credit / udhaar; payment = customer paid money)
- Shop "what did you sell?" labels (item_key MUST be copied character-for-character from this list when a product is named): {$quickJson}
- Write "summary" in {$summaryLang} (one short line for confirm UI; include the sold item name when known).

Return ONLY valid JSON (no markdown) with exactly these keys:
- "type": "credit" or "payment" (prefer intent_hint unless transcript clearly means the opposite; then use transcript and set confidence "low")
- "amount_sen": integer smallest currency units (e.g. MYR/PKR/USD: major * 100; JPY/KRW: major amount as integer with no extra *100)
- "note": string or null (extra context only, max 120 chars; do not duplicate the quick item label here if item_key is set)
- "next_due_at": "YYYY-MM-DD" or null (instalment / due hints like "next week", "7 days")
- "item_key": string or null — for credit/udhaar: pick the closest label from the shop list when the user says what they sold (e.g. rice/beras/chawal → "Rice" if that label exists). Must match a list entry exactly.
- "confidence": "high", "medium", or "low"
- "summary": one short confirm line in {$summaryLang}

If amount cannot be determined, set amount_sen to null and confidence to "low".

Transcript:
{$transcript}
PROMPT;

        $payload = [
            'contents' => [[
                'role' => 'user',
                'parts' => [['text' => $prompt]],
            ]],
            'generationConfig' => [
                'temperature' => 0.1,
                'maxOutputTokens' => 384,
            ],
        ];

        $models = $this->modelsToTry();
        $lastResponse = null;

        foreach ($models as $model) {
            $resp = $this->generateContent($key, $model, $payload);
            $lastResponse = $resp;

            if ($resp->successful()) {
                return $this->parseSuccessfulResponse($resp->json(), $intentHint, $quickItems, $transcript);
            }

            $errMsg = strtolower((string) ($resp->json('error.message') ?? ''));
            $status = $resp->status();

            $tryNext = $status === 404
                || $status === 429
                || $status === 503
                || str_contains($errMsg, 'not found')
                || str_contains($errMsg, 'not supported')
                || str_contains($errMsg, 'quota')
                || str_contains($errMsg, 'resource_exhausted');

            Log::warning('Gemini voice ledger parse HTTP error', [
                'model' => $model,
                'status' => $status,
            ]);

            if (! $tryNext) {
                break;
            }
        }

        return $this->emptyResult('gemini_request_failed');
    }

    /**
     * @param  list<string>  $quickItems
     * @return array{
     *     type: ?string,
     *     amount_sen: ?int,
     *     note: ?string,
     *     next_due_at: ?string,
     *     item_key: ?string,
     *     confidence: ?string,
     *     summary: ?string,
     *     error: ?string
     * }
     */
    private function parseSuccessfulResponse(?array $json, string $intentHint, array $quickItems, string $transcript): array
    {
        if ($json === null) {
            return $this->emptyResult('gemini_request_failed');
        }

        $blockReason = $json['promptFeedback']['blockReason'] ?? null;
        if (is_string($blockReason) && $blockReason !== '') {
            Log::warning('Gemini voice ledger: blocked', ['reason' => $blockReason]);

            return $this->emptyResult('gemini_blocked');
        }

        $text = $this->extractTextFromCandidates($json);
        if ($text === null || trim($text) === '') {
            return $this->emptyResult(null);
        }

        $parsed = $this->decodeModelJson($text);
        if (! is_array($parsed)) {
            return $this->emptyResult('gemini_parse_failed');
        }

        $type = null;
        if (isset($parsed['type']) && is_string($parsed['type'])) {
            $t = strtolower(trim($parsed['type']));
            if (in_array($t, ['credit', 'payment'], true)) {
                $type = $t;
            }
        }
        if ($type === null) {
            $type = in_array($intentHint, ['credit', 'payment'], true) ? $intentHint : 'credit';
        }

        $sen = null;
        if (isset($parsed['amount_sen']) && is_numeric($parsed['amount_sen'])) {
            $sen = max(0, (int) round((float) $parsed['amount_sen']));
        }
        if ($sen !== null && ($sen <= 0 || $sen > 999_999_999_999)) {
            $sen = null;
        }

        $note = null;
        if (isset($parsed['note']) && is_string($parsed['note'])) {
            $n = trim($parsed['note']);
            $note = $n === '' ? null : (mb_strlen($n) <= 120 ? $n : mb_substr($n, 0, 117).'…');
        }

        $nextDue = null;
        if (isset($parsed['next_due_at']) && is_string($parsed['next_due_at'])) {
            $d = trim($parsed['next_due_at']);
            $nextDue = preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) === 1 ? $d : null;
        }

        $rawItemKey = isset($parsed['item_key']) && is_string($parsed['item_key'])
            ? trim($parsed['item_key'])
            : null;
        $itemKey = $this->resolveQuickItemKey($rawItemKey, $note, $transcript, $quickItems);

        $confidence = 'low';
        if (isset($parsed['confidence']) && is_string($parsed['confidence'])) {
            $c = strtolower(trim($parsed['confidence']));
            if (in_array($c, ['high', 'medium', 'low'], true)) {
                $confidence = $c;
            }
        }
        if ($sen === null) {
            $confidence = 'low';
        }

        $summary = null;
        if (isset($parsed['summary']) && is_string($parsed['summary'])) {
            $s = trim($parsed['summary']);
            $summary = $s === '' ? null : (mb_strlen($s) <= 160 ? $s : mb_substr($s, 0, 157).'…');
        }

        return [
            'type' => $type,
            'amount_sen' => $sen,
            'note' => $note,
            'next_due_at' => $nextDue,
            'item_key' => $itemKey,
            'confidence' => $confidence,
            'summary' => $summary,
            'error' => null,
        ];
    }

    /**
     * @return array{
     *     type: ?string,
     *     amount_sen: ?int,
     *     note: ?string,
     *     next_due_at: ?string,
     *     item_key: ?string,
     *     confidence: ?string,
     *     summary: ?string,
     *     error: ?string
     * }
     */
    private function emptyResult(?string $error): array
    {
        return [
            'type' => null,
            'amount_sen' => null,
            'note' => null,
            'next_due_at' => null,
            'item_key' => null,
            'confidence' => 'low',
            'summary' => null,
            'error' => $error,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function generateContent(string $apiKey, string $model, array $payload): \Illuminate\Http\Client\Response
    {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'.rawurlencode($model).':generateContent';

        return Http::timeout(55)->post($url.'?key='.urlencode($apiKey), $payload);
    }

    /**
     * @return list<string>
     */
    private function modelsToTry(): array
    {
        $preferred = trim($this->preferredModel);

        return array_values(array_unique(array_filter([
            $preferred !== '' ? $preferred : null,
            'gemini-2.5-flash-lite',
            'gemini-2.5-flash',
            'gemini-2.0-flash',
            'gemini-1.5-flash-8b',
        ])));
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function extractTextFromCandidates(array $json): ?string
    {
        $candidates = $json['candidates'] ?? null;
        if (! is_array($candidates) || $candidates === []) {
            return null;
        }

        $first = $candidates[0] ?? null;
        if (! is_array($first)) {
            return null;
        }

        $parts = $first['content']['parts'] ?? null;
        if (! is_array($parts) || $parts === []) {
            return null;
        }

        $text = $parts[0]['text'] ?? null;

        return is_string($text) ? $text : null;
    }

    /**
     * Map Gemini / spoken product names to a shop quick-item label.
     *
     * @param  list<string>  $quickItems
     */
    private function resolveQuickItemKey(?string $fromModel, ?string $note, string $transcript, array $quickItems): ?string
    {
        if ($quickItems === []) {
            return null;
        }

        $candidates = array_filter([
            $fromModel !== null && $fromModel !== '' ? $fromModel : null,
            $note,
            $transcript,
        ]);

        foreach ($quickItems as $label) {
            if ($fromModel !== null && strcasecmp($fromModel, $label) === 0) {
                return $label;
            }
        }

        foreach ($candidates as $text) {
            $lower = mb_strtolower($text);
            foreach ($quickItems as $label) {
                $labelLower = mb_strtolower($label);
                if ($labelLower === '') {
                    continue;
                }
                if (str_contains($lower, $labelLower)) {
                    return $label;
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeModelJson(string $text): ?array
    {
        $trimmed = trim($text);
        $trimmed = preg_replace('/^\s*```(?:json)?\s*/i', '', $trimmed) ?? $trimmed;
        $trimmed = preg_replace('/\s*```\s*$/', '', $trimmed) ?? $trimmed;
        $trimmed = trim($trimmed);
        /** @var mixed $decoded */
        $decoded = json_decode($trimmed, true);

        return is_array($decoded) ? $decoded : null;
    }
}
