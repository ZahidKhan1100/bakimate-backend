<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_email_links_to_https_page_not_app_scheme(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'reset@example.com']);

        $this->postJson('/api/auth/forgot-password', ['email' => 'reset@example.com'])->assertOk();

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use ($user) {
            $url = $n->toMail($user)->actionUrl;

            return str_starts_with($url, config('app.url').'/reset-password/')
                && str_contains($url, 'email=reset%40example.com');
        });
    }

    public function test_page_offers_app_deep_link_and_web_form(): void
    {
        $user = User::factory()->create(['email' => 'reset@example.com']);
        $token = Password::createToken($user);

        $this->get('/reset-password/'.$token.'?email=reset%40example.com')
            ->assertOk()
            ->assertSee('bakimate://reset-password?token='.$token, false)
            ->assertSee('name="password_confirmation"', false);
    }

    public function test_web_form_resets_password(): void
    {
        $user = User::factory()->create(['email' => 'reset@example.com']);
        $token = Password::createToken($user);

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post('/reset-password', [
                'token' => $token,
                'email' => 'reset@example.com',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertOk()
            ->assertSee('Password updated');

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
    }

    public function test_web_form_rejects_bad_token(): void
    {
        User::factory()->create(['email' => 'reset@example.com']);

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post('/reset-password', [
                'token' => 'not-a-real-token',
                'email' => 'reset@example.com',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertOk()
            ->assertSee('invalid or has expired');
    }
}
