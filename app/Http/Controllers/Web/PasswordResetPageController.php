<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

/**
 * HTTPS landing page for the password reset email.
 *
 * Email apps (Gmail, Outlook) don't open custom-scheme links like bakimate://, so the email
 * links here instead. The page offers "Open in BakiMate" (the app's reset screen via deep link)
 * and a plain web form that resets the password directly, so it works on any device.
 */
class PasswordResetPageController extends Controller
{
    public function show(Request $request, string $token): View
    {
        $email = (string) $request->query('email', '');

        return view('auth.reset-password', [
            'token' => $token,
            'email' => $email,
            'appLink' => self::appLink($token, $email),
        ]);
    }

    public function update(Request $request): View
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'token' => ['required', 'string', 'max:500'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::reset(
            $data + ['password_confirmation' => $request->input('password_confirmation')],
            function (User $user, string $password): void {
                $user->forceFill(['password' => $password]);
                /** The reset link came to this inbox, so the address is proven — no separate verification needed. */
                $user->email_verified_at ??= now();
                $user->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return view('auth.reset-password', [
                'token' => $data['token'],
                'email' => $data['email'],
                'appLink' => self::appLink($data['token'], $data['email']),
                'linkError' => true,
            ]);
        }

        return view('auth.reset-password-done');
    }

    /** Deep link into the app's reset screen (same format the email used to carry). */
    public static function appLink(string $token, string $email): string
    {
        return sprintf('%s://%s?%s',
            config('bakimate.password_reset_app_scheme', 'bakimate'),
            config('bakimate.password_reset_app_path', 'reset-password'),
            http_build_query(['token' => $token, 'email' => $email]),
        );
    }
}
