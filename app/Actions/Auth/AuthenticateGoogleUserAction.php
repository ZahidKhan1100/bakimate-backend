<?php

namespace App\Actions\Auth;

use App\Models\Shop;
use App\Models\User;
use App\Services\GoogleIdTokenService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class AuthenticateGoogleUserAction
{
    public function __construct(
        private readonly GoogleIdTokenService $googleIdToken,
    ) {}

    public function execute(string $idToken): User
    {
        $claims = $this->googleIdToken->verifyAndExtract($idToken);

        return DB::transaction(function () use ($claims) {
            $user = User::query()->where('google_sub', $claims['sub'])->first();

            if ($user) {
                $user->fill([
                    'name' => $claims['name'],
                    'email' => $claims['email'],
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ]);
                $user->save();

                return $user;
            }

            /** Match existing email/password (or OAuth) account — avoid duplicate-email insert. */
            $normalizedEmail = mb_strtolower($claims['email']);
            /** @var User|null $byEmail */
            $byEmail = User::query()->whereRaw('LOWER(email) = ?', [$normalizedEmail])->first();

            if ($byEmail !== null) {
                $existingSub = $byEmail->google_sub;
                $hasOtherGoogleAccount = self::filledString($existingSub) && $existingSub !== $claims['sub'];
                if ($hasOtherGoogleAccount) {
                    throw new \RuntimeException(
                        'This email is linked to another Google account. Sign in with that provider or use email and password.',
                    );
                }

                $byEmail->google_sub = $claims['sub'];
                $byEmail->name = $claims['name'] !== '' ? $claims['name'] : $byEmail->name;
                $byEmail->email = $claims['email'];
                if ($byEmail->email_verified_at === null) {
                    $byEmail->email_verified_at = now();
                }
                $byEmail->save();

                return $byEmail;
            }

            try {
                $user = User::query()->create([
                    'name' => $claims['name'],
                    'email' => $claims['email'],
                    'google_sub' => $claims['sub'],
                    'password' => null,
                    'email_verified_at' => now(),
                ]);
            } catch (QueryException) {
                throw new \RuntimeException(
                    'This email already has an account. Try signing in with your email and password, or Google if that account matches.',
                );
            }

            Shop::query()->create([
                'user_id' => $user->id,
                'name' => 'My Shop',
                'primary_currency_code' => 'MYR',
                'subscription_expires_at' => now()->addDays(90),
                'credit_quick_items' => Shop::DEFAULT_CREDIT_QUICK_ITEMS,
            ]);

            return $user;
        });
    }

    private static function filledString(?string $v): bool
    {
        return $v !== null && $v !== '';
    }
}
