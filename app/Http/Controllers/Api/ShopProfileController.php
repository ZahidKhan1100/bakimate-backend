<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateShopProfileRequest;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ShopProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $shop = $request->user()?->shops()->firstOrFail();

        return response()->json($this->serializeShop($shop));
    }

    public function update(UpdateShopProfileRequest $request): JsonResponse
    {
        $shop = $request->user()?->shops()->firstOrFail();
        $validated = $request->validated();

        /** Coerce intentional clears / null payloads so the column stays a non-empty ISO code (default MYR). */
        if (\array_key_exists('primary_currency_code', $validated)
            && ($validated['primary_currency_code'] === null
                || (is_string($validated['primary_currency_code']) && trim($validated['primary_currency_code']) === ''))) {
            $validated['primary_currency_code'] = 'MYR';
        }

        $shop->update($validated);

        return response()->json($this->serializeShop($shop->fresh()));
    }

    /** @return array<string, mixed> */
    public static function serializeShop(Shop $shop): array
    {
        return [
            'id' => $shop->id,
            'name' => $shop->name,
            'primary_currency_code' => $shop->primary_currency_code !== null && $shop->primary_currency_code !== ''
                ? strtoupper(substr((string) $shop->primary_currency_code, 0, 8))
                : 'MYR',
            'location' => $shop->location,
            'contact' => $shop->contact,
            'payment_instructions' => $shop->payment_instructions,
            'credit_quick_items' => self::effectiveQuickItems($shop),
            'reference_currency_code' => $shop->reference_currency_code,
            'reference_currency_per_myr' => $shop->reference_currency_per_myr !== null
                ? (float) $shop->reference_currency_per_myr
                : null,
            /** Server-side entitlement (gates premium API routes); ISO-8601 in app timezone cast to UTC typical. */
            'subscription_active' => $shop->subscriptionActive(),
            'subscription_expires_at' => $shop->subscription_expires_at !== null
                ? $shop->subscription_expires_at->toIso8601String()
                : null,
            'duitnow_qr_url' => $shop->duitnow_qr_path
                ? rtrim((string) config('app.url'), '/').'/media/'.$shop->duitnow_qr_path
                : null,
        ];
    }

    /**
     * @return list<string>
     */
    public static function effectiveQuickItems(Shop $shop): array
    {
        $raw = $shop->credit_quick_items;
        if (! is_array($raw) || $raw === []) {
            return Shop::DEFAULT_CREDIT_QUICK_ITEMS;
        }

        $out = [];
        foreach ($raw as $s) {
            if (is_string($s)) {
                $t = trim($s);
                if ($t !== '') {
                    $out[] = $t;
                }
            }
        }

        $uniq = array_values(array_unique($out));

        return $uniq !== [] ? $uniq : Shop::DEFAULT_CREDIT_QUICK_ITEMS;
    }
}
