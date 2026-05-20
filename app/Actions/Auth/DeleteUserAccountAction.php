<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteUserAccountAction
{
    /**
     * Permanently remove the authenticated user, their owned shops (cascade deletes ledger data),
     * API tokens, and uploaded DuitNow assets.
     *
     * @throws \RuntimeException when the seeded demo identity must stay available
     */
    public function execute(User $user): void
    {
        if (
            strtolower((string) $user->email) === 'demo@bakimate.test'
            && config('bakimate.demo_login_enabled') === true
        ) {
            throw new \RuntimeException('Demo account cannot be deleted while demo login is enabled.');
        }

        DB::transaction(function () use ($user): void {
            $user->load('shops');

            foreach ($user->shops as $shop) {
                if ($shop->duitnow_qr_path !== null && $shop->duitnow_qr_path !== '') {
                    Storage::disk('public')->delete($shop->duitnow_qr_path);
                }
            }

            $user->tokens()->delete();
            $user->delete();
        });
    }
}
