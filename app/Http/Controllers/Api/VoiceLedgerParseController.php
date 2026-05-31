<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVoiceLedgerParseRequest;
use App\Services\GeminiVoiceLedgerParseService;
use Illuminate\Http\JsonResponse;

class VoiceLedgerParseController extends Controller
{
    public function __invoke(StoreVoiceLedgerParseRequest $request): JsonResponse
    {
        $validated = $request->validated();

        /** @var list<string> $quickItems */
        $quickItems = array_values(array_filter(
            array_map(
                static fn ($v) => is_string($v) ? trim($v) : '',
                $validated['quick_items'] ?? [],
            ),
            static fn (string $s) => $s !== '',
        ));

        $svc = GeminiVoiceLedgerParseService::fromConfig();

        $parsed = $svc->parseFromTranscript(
            (string) $validated['transcript'],
            (string) $validated['intent_hint'],
            strtoupper((string) $validated['currency_code']),
            (string) $validated['customer_name'],
            $quickItems,
            (string) ($validated['app_language'] ?? 'en'),
        );

        return response()->json([
            'type' => $parsed['type'] ?? null,
            'amount_sen' => $parsed['amount_sen'] ?? null,
            'note' => $parsed['note'] ?? null,
            'next_due_at' => $parsed['next_due_at'] ?? null,
            'item_key' => $parsed['item_key'] ?? null,
            'confidence' => $parsed['confidence'] ?? 'low',
            'summary' => $parsed['summary'] ?? null,
            'error_code' => $parsed['error'] ?? null,
        ]);
    }
}
