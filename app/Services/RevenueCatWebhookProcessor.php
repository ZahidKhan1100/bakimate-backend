<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Applies RevenueCat subscription webhook events to shops (App Store & Google Play Billing route through RC).
 */
final class RevenueCatWebhookProcessor
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function processWebhookPayload(array $payload): void
    {
        $event = $this->extractEvent($payload);
        if ($event === null) {
            Log::warning('subscription.webhook.missing_event', ['payload_keys' => array_keys($payload)]);

            return;
        }

        $type = isset($event['type']) ? (string) $event['type'] : '';

        if ($eventId = isset($event['id']) ? (string) $event['id'] : '') {
            $cacheKey = 'revenuecat:webhook:event:'.$eventId;
            if (Cache::add($cacheKey, 1, now()->addDay()) === false) {
                Log::info('subscription.webhook.duplicate_ignored', ['event_id' => $eventId, 'type' => $type]);

                return;
            }
        }

        Log::info('subscription.webhook.event', [
            'type' => $type,
            'store' => $event['store'] ?? null,
            'environment' => $event['environment'] ?? null,
        ]);

        if ($type === 'TEST') {
            return;
        }

        if ($type === '' || $type === 'SUBSCRIBER_ALIAS') {
            return;
        }

        if ($type === 'TRANSFER') {
            $this->processTransferExpiry($event);

            return;
        }

        if (in_array($type, [
            'EXPERIMENT_ENROLLMENT',
            'VIRTUAL_CURRENCY_TRANSACTION',
            'INVOICE_ISSUANCE',
        ], true)) {
            return;
        }

        /** Billing issues retain access until entitlement expiry unless `expiration_at_ms` is omitted. */
        if ($type === 'BILLING_ISSUE' && empty($event['expiration_at_ms'])) {
            return;
        }

        if ($type === 'SUBSCRIPTION_PAUSED' && empty($event['expiration_at_ms'])) {
            return;
        }

        $expirationMs = $event['expiration_at_ms'] ?? null;

        foreach ($this->numericAppUserIds($event) as $appUserId) {
            $user = User::query()->find((int) $appUserId);
            if ($user === null) {
                Log::notice('subscription.webhook.unknown_app_user_id', [
                    'app_user_id' => $appUserId,
                    'type' => $type,
                    'store' => $event['store'] ?? null,
                ]);

                continue;
            }

            $shop = $user->shops()->orderBy('id')->first();
            if ($shop === null) {
                Log::notice('subscription.webhook.user_without_shop', ['user_id' => $user->id]);

                continue;
            }

            if ($expirationMs === null || $expirationMs === '') {
                if ($type === 'EXPIRATION') {
                    $shop->subscription_expires_at = now();
                    $shop->save();

                    Log::info('subscription.webhook.expired_no_ms', ['shop_id' => $shop->id]);
                }

                continue;
            }

            $expiresAt = Carbon::createFromTimestampMs((int) $expirationMs);

            $extendsExpiry = [
                'INITIAL_PURCHASE',
                'RENEWAL',
                'UNCANCELLATION',
                'PRODUCT_CHANGE',
                'SUBSCRIPTION_EXTENDED',
                'TEMPORARY_ENTITLEMENT_GRANT',
                'NON_RENEWING_PURCHASE',
                'REFUND_REVERSED',
                'SUBSCRIPTION_PAUSED',
                'BILLING_ISSUE',
                'CANCELLATION',
                'EXPIRATION',
            ];

            if (in_array($type, $extendsExpiry, true)) {
                $shop->subscription_expires_at = $expiresAt;
                $shop->save();

                Log::info('subscription.webhook.updated_shop_expiry', [
                    'shop_id' => $shop->id,
                    'user_id' => $user->id,
                    'subscription_expires_at' => $shop->subscription_expires_at?->toIso8601String(),
                    'store' => $event['store'] ?? null,
                ]);

                continue;
            }

            Log::notice('subscription.webhook.unhandled_type', ['type' => $type, 'shop_id' => $shop->id]);
        }
    }

    /** @param  array<string, mixed>  $payload */
    private function extractEvent(array $payload): ?array
    {
        if (isset($payload['event']) && is_array($payload['event'])) {
            return $payload['event'];
        }

        if (isset($payload['type']) && is_string($payload['type'])) {
            return $payload;
        }

        return null;
    }

    /**
     * BakiMate maps Laravel `users.id` to RevenueCat App User IDs on mobile (`Purchases.logIn(String(user.id))`).
     *
     * @param  array<string, mixed>  $event
     * @return list<string> decimal strings compatible with {@see User::query()->find()}
     */
    private function numericAppUserIds(array $event): array
    {
        $raw = [];

        foreach (['app_user_id', 'original_app_user_id'] as $k) {
            if (isset($event[$k]) && trim((string) $event[$k]) !== '') {
                $raw[] = (string) $event[$k];
            }
        }

        $aliasesList = isset($event['aliases']) && is_array($event['aliases']) ? $event['aliases'] : [];

        foreach ($aliasesList as $alias) {
            if (trim((string) $alias) !== '') {
                $raw[] = (string) $alias;
            }
        }

        $numeric = [];
        foreach ($raw as $candidate) {
            $t = trim($candidate);
            if ($t !== '' && ctype_digit($t)) {
                $numeric[] = $t;
            }
        }

        return array_values(array_unique($numeric));
    }

    /** @param  array<string, mixed>  $event */
    private function processTransferExpiry(array $event): void
    {
        $expirationMs = $event['expiration_at_ms'] ?? null;
        if ($expirationMs === null || $expirationMs === '') {
            return;
        }

        $toRaw = $event['transferred_to'] ?? [];
        $toRaw = is_array($toRaw) ? $toRaw : [];

        $expiresAt = Carbon::createFromTimestampMs((int) $expirationMs);

        foreach ($toRaw as $id) {
            $t = trim((string) $id);
            if ($t === '' || ! ctype_digit($t)) {
                continue;
            }
            $user = User::query()->find((int) $t);
            $shop = $user?->shops()->orderBy('id')->first();
            if ($shop === null) {
                continue;
            }
            $shop->subscription_expires_at = $expiresAt;
            $shop->save();
        }
    }
}
