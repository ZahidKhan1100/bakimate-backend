<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VoiceLedgerParseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.gemini.api_key' => 'test-gemini-key']);
        config(['services.gemini.model' => 'gemini-2.5-flash-lite']);
    }

    public function test_voice_ledger_parse_returns_structured_json(): void
    {
        $user = User::factory()->create();
        Shop::query()->create([
            'user_id' => $user->id,
            'name' => 'Test Shop',
            'primary_currency_code' => 'MYR',
            'subscription_expires_at' => now()->addDays(7),
            'credit_quick_items' => ['Rice', 'Oil'],
        ]);

        Sanctum::actingAs($user);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => json_encode([
                                'type' => 'credit',
                                'amount_sen' => 50000,
                                'note' => 'Rice',
                                'next_due_at' => null,
                                'item_key' => 'Rice',
                                'confidence' => 'high',
                                'summary' => 'RM 500 credit — Rice',
                            ], JSON_THROW_ON_ERROR),
                        ]],
                    ],
                ]],
            ], 200),
        ]);

        $response = $this->postJson('/api/voice-ledger-parse', [
            'transcript' => 'hutang lima ratus beras',
            'intent_hint' => 'credit',
            'currency_code' => 'MYR',
            'customer_name' => 'Ahmad',
            'quick_items' => ['Rice', 'Oil'],
            'app_language' => 'en',
        ]);

        $response->assertOk()
            ->assertJson([
                'type' => 'credit',
                'amount_sen' => 50000,
                'note' => 'Rice',
                'item_key' => 'Rice',
                'confidence' => 'high',
                'error_code' => null,
            ]);
    }

    public function test_voice_ledger_parse_requires_auth(): void
    {
        $response = $this->postJson('/api/voice-ledger-parse', [
            'transcript' => 'paid three hundred',
            'intent_hint' => 'payment',
            'currency_code' => 'MYR',
            'customer_name' => 'Ali',
        ]);

        $response->assertUnauthorized();
    }

    public function test_voice_ledger_parse_validates_transcript(): void
    {
        $user = User::factory()->create();
        Shop::query()->create([
            'user_id' => $user->id,
            'name' => 'Test Shop',
            'primary_currency_code' => 'MYR',
            'subscription_expires_at' => now()->addDays(7),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/voice-ledger-parse', [
            'transcript' => '',
            'intent_hint' => 'credit',
            'currency_code' => 'MYR',
            'customer_name' => 'Ali',
        ]);

        $response->assertStatus(422);
    }

    public function test_voice_ledger_parse_resolves_quick_item_case_insensitive(): void
    {
        $user = User::factory()->create();
        Shop::query()->create([
            'user_id' => $user->id,
            'name' => 'Test Shop',
            'primary_currency_code' => 'MYR',
            'subscription_expires_at' => now()->addDays(7),
            'credit_quick_items' => ['Rice', 'Oil'],
        ]);

        Sanctum::actingAs($user);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => json_encode([
                                'type' => 'credit',
                                'amount_sen' => 30000,
                                'note' => null,
                                'next_due_at' => null,
                                'item_key' => 'rice',
                                'confidence' => 'high',
                                'summary' => 'RM 300 credit — Rice',
                            ], JSON_THROW_ON_ERROR),
                        ]],
                    ],
                ]],
            ], 200),
        ]);

        $response = $this->postJson('/api/voice-ledger-parse', [
            'transcript' => 'udhaar teen sau chawal',
            'intent_hint' => 'credit',
            'currency_code' => 'MYR',
            'customer_name' => 'Ali',
            'quick_items' => ['Rice', 'Oil'],
            'app_language' => 'en',
        ]);

        $response->assertOk()->assertJson([
            'item_key' => 'Rice',
            'amount_sen' => 30000,
        ]);
    }

    public function test_voice_ledger_parse_payment_forces_type_and_strips_item_key(): void
    {
        $user = User::factory()->create();
        Shop::query()->create([
            'user_id' => $user->id,
            'name' => 'Test Shop',
            'primary_currency_code' => 'PKR',
            'subscription_expires_at' => now()->addDays(7),
            'credit_quick_items' => ['Rice'],
        ]);

        Sanctum::actingAs($user);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => json_encode([
                                'type' => 'credit',
                                'amount_sen' => 45000,
                                'note' => null,
                                'next_due_at' => '2026-06-01',
                                'item_key' => 'Rice',
                                'confidence' => 'low',
                                'summary' => 'PKR 450 credit',
                            ], JSON_THROW_ON_ERROR),
                        ]],
                    ],
                ]],
            ], 200),
        ]);

        $response = $this->postJson('/api/voice-ledger-parse', [
            'transcript' => 'teen sau wusool',
            'intent_hint' => 'payment',
            'currency_code' => 'PKR',
            'customer_name' => 'Hassan',
            'quick_items' => ['Rice'],
            'app_language' => 'en',
        ]);

        $response->assertOk()->assertJson([
            'type' => 'payment',
            'amount_sen' => 45000,
            'item_key' => null,
            'next_due_at' => null,
            'confidence' => 'medium',
        ]);
    }

    public function test_voice_ledger_parse_payment_extracts_amount_when_model_omits_it(): void
    {
        $user = User::factory()->create();
        Shop::query()->create([
            'user_id' => $user->id,
            'name' => 'Test Shop',
            'primary_currency_code' => 'MYR',
            'subscription_expires_at' => now()->addDays(7),
        ]);

        Sanctum::actingAs($user);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => json_encode([
                                'type' => 'payment',
                                'amount_sen' => null,
                                'note' => null,
                                'next_due_at' => null,
                                'item_key' => null,
                                'confidence' => 'low',
                                'summary' => null,
                            ], JSON_THROW_ON_ERROR),
                        ]],
                    ],
                ]],
            ], 200),
        ]);

        $response = $this->postJson('/api/voice-ledger-parse', [
            'transcript' => 'paid 250 wusool',
            'intent_hint' => 'payment',
            'currency_code' => 'MYR',
            'customer_name' => 'Ali',
            'app_language' => 'en',
        ]);

        $response->assertOk()->assertJson([
            'type' => 'payment',
            'amount_sen' => 25000,
            'confidence' => 'medium',
        ]);
    }

    public function test_voice_ledger_parse_when_gemini_not_configured(): void
    {
        config(['services.gemini.api_key' => '']);

        $user = User::factory()->create();
        Shop::query()->create([
            'user_id' => $user->id,
            'name' => 'Test Shop',
            'primary_currency_code' => 'PKR',
            'subscription_expires_at' => now()->addDays(7),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/voice-ledger-parse', [
            'transcript' => 'teen sau wusool',
            'intent_hint' => 'payment',
            'currency_code' => 'PKR',
            'customer_name' => 'Hassan',
        ]);

        $response->assertOk()
            ->assertJson([
                'amount_sen' => null,
                'confidence' => 'low',
                'error_code' => 'gemini_not_configured',
            ]);
    }
}
