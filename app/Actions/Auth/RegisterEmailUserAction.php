<?php

namespace App\Actions\Auth;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegisterEmailUserAction
{
    /**
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function execute(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

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
