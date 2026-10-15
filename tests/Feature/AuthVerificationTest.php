<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_password_reset_also_verifies_email(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'shop@example.com']);
        $token = Password::createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'email' => 'shop@example.com',
            'token' => $token,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertOk();

        $this->assertNotNull($user->fresh()->email_verified_at);

        $this->postJson('/api/auth/login', ['email' => 'shop@example.com', 'password' => 'new-password-123'])
            ->assertOk()
            ->assertJsonStructure(['token']);
    }

    public function test_web_password_reset_also_verifies_email(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'shop@example.com']);
        $token = Password::createToken($user);

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post('/reset-password', [
                'token' => $token,
                'email' => 'shop@example.com',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])->assertOk();

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_unverified_login_sends_verification_email_once_per_window(): void
    {
        // No Mailgun credentials in tests: the sender logs this error instead of calling the API,
        // which tells us a send was attempted without hitting the network.
        Config::set('services.mailgun.domain', '');
        Log::spy();

        User::factory()->unverified()->create(['email' => 'shop@example.com', 'password' => 'secret-pass-1']);

        $login = fn () => $this->postJson('/api/auth/login', ['email' => 'shop@example.com', 'password' => 'secret-pass-1']);
        $login()->assertStatus(403)->assertJson(['email_verified' => false]);
        $login()->assertStatus(403);

        Log::shouldHaveReceived('error')->with('Mailgun cURL: MAILGUN_DOMAIN or MAILGUN_SECRET missing')->once();
    }
}
