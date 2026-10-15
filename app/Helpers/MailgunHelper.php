<?php

use Illuminate\Support\Facades\Log;

if (! function_exists('sendMailgunEmail')) {
    /**
     * HabiMate-style direct Mailgun HTTPS API (curl) — same env keys as symfony/mailgun-mailer.
     */
    function sendMailgunEmail(string $to, string $subject, string $html, ?string $text = null): bool
    {
        /** Read config(), not env(): env() returns null once `php artisan config:cache` has run in production. */
        $domain = trim((string) config('services.mailgun.domain'));
        $apiKey = trim((string) config('services.mailgun.secret'));
        $from = trim((string) config('mail.from.address', 'noreply@example.com'));
        $fromName = trim((string) config('mail.from.name', config('app.name', 'BakiMate')));

        $host = preg_replace('#^https?://#i', '', trim((string) config('services.mailgun.endpoint', 'api.mailgun.net')));
        if ($host === '') {
            $host = 'api.mailgun.net';
        }

        if ($domain === '' || $apiKey === '') {
            Log::error('Mailgun cURL: MAILGUN_DOMAIN or MAILGUN_SECRET missing');

            return false;
        }

        $endpoint = "https://{$host}/v3/{$domain}/messages";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, 'api:'.$apiKey);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'from' => "{$fromName} <{$from}>",
            'to' => $to,
            'subject' => $subject,
            'text' => $text ?? strip_tags($html),
            'html' => $html,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            Log::error('Mailgun cURL transport error', ['curl_error' => $error]);

            return false;
        }

        $decoded = is_string($response) ? json_decode($response, true) : null;
        Log::info('Mailgun send finished', [
            'has_id' => is_array($decoded) && ! empty($decoded['id']),
            'message' => is_array($decoded) ? ($decoded['message'] ?? null) : null,
        ]);

        return true;
    }
}
