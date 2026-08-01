<?php

namespace App\Actions\Auth;

use App\Models\Shop;
use App\Models\User;
use App\Services\AppleIdTokenService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class AuthenticateAppleUserAction
{
    public function __construct(
        private readonly AppleIdTokenService $appleIdToken,
    ) {}

    public function execute(string $idToken, ?string $fullName): User
    {
        $claims = $this->appleIdToken->verifyAndExtract($idToken);

        return DB::transaction(function () use ($claims, $fullName): User {
            $user = User::query()->where('apple_sub', $claims['sub'])->first();

            if ($user) {
                if (($claims['email'] ?? null) && $claims['email'] !== $user->email) {
                    $user->email = $claims['email'];
                }
                if ($fullName !== null && trim($fullName) !== '' && trim($fullName) !== $user->name) {
                    $user->name = trim($fullName);
                }
                if ($user->email_verified_at === null) {
                    $user->email_verified_at = now();
                }
                $user->save();

                return $user;
            }

            $email = $claims['email'];
            if (! is_string($email) || $email === '') {
                $email = 'apple.'.hash('sha256', $claims['sub']).'@bakimate.invalid';
            }

            $name = trim((string) ($fullName ?? ''));
            if ($name === '') {
                $name = 'Apple';
            }

            try {
                $user = User::query()->create([
                    'name' => $name,
                    'email' => $email,
                    'apple_sub' => $claims['sub'],
                    'password' => null,
                    'email_verified_at' => now(),
                ]);
            } catch (QueryException) {
                throw new \RuntimeException(
                    'This email may already belong to another sign-in provider. Try Google login instead.',
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
}
