<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

final class SubscriptionWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function actingUserWithShop(?Carbon $expires = null): User
    {
        $user = User::factory()->create();
        Shop::query()->create([
            'user_id' => $user->id,
            'name' => 'Test Shop',
            'primary_currency_code' => 'MYR',
            'subscription_expires_at' => $expires ?? now()->subDay(),
            'credit_quick_items' => Shop::DEFAULT_CREDIT_QUICK_ITEMS,
        ]);

        return $user;
    }

    public function test_subscription_webhook_rejects_invalid_authorization(): void
    {
        Config::set('services.revenuecat.webhook_secret', 'expected-secret');

        $this->post('/api/webhooks/subscription', [], [
            'Authorization' => 'wrong',
        ])
            ->assertStatus(403);
    }

    public function test_subscription_webhook_accepts_matching_authorization_header(): void
    {
        Config::set('services.revenuecat.webhook_secret', 'expected-secret');

        $this->post('/api/webhooks/subscription', [], [
            'Authorization' => 'Bearer expected-secret',
        ])
            ->assertOk();
    }

    public function test_play_store_initial_purchase_updates_subscription_expiry(): void
    {
        Config::set('services.revenuecat.webhook_secret', '');

        $user = $this->actingUserWithShop();
        $expected = Carbon::parse('2032-06-01T00:00:00+00:00');

        /** Carbon → ms since epoch */
        $expiryMs = (int) round($expected->timestamp * 1000);

        $this->postJson('/api/webhooks/subscription', [
            'api_version' => '1.0',
            'event' => [
                'type' => 'INITIAL_PURCHASE',
                'id' => 'evt_rc_play_1',
                'app_user_id' => (string) $user->id,
                'store' => 'PLAY_STORE',
                'environment' => 'SANDBOX',
                'expiration_at_ms' => $expiryMs,
            ],
        ])->assertOk();

        $shop = $user->fresh()?->shops()->first();
        $this->assertNotNull($shop);
        $this->assertTrue($expected->equalTo($shop->subscription_expires_at));
    }

    public function test_duplicate_webhook_event_id_is_ignored_second_time(): void
    {
        Config::set('services.revenuecat.webhook_secret', '');

        $user = $this->actingUserWithShop();
        $firstExpiry = Carbon::parse('2030-01-01T12:00:00+00:00');
        $firstMs = (int) round($firstExpiry->timestamp * 1000);
        $secondExpiry = Carbon::parse('2035-01-01T12:00:00+00:00');
        $secondMs = (int) round($secondExpiry->timestamp * 1000);

        $body = fn (int $expiryMs) => [
            'event' => [
                'type' => 'INITIAL_PURCHASE',
                'id' => 'evt_rc_dup',
                'app_user_id' => (string) $user->id,
                'store' => 'PLAY_STORE',
                'expiration_at_ms' => $expiryMs,
            ],
        ];

        $this->postJson('/api/webhooks/subscription', $body($firstMs))->assertOk();
        $this->postJson('/api/webhooks/subscription', $body($secondMs))->assertOk();

        $shop = $user->fresh()?->shops()->first();
        $this->assertNotNull($shop);
        $this->assertTrue($firstExpiry->equalTo($shop->subscription_expires_at));
    }
}
