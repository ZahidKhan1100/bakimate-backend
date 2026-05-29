<?php

use App\Http\Controllers\Web\PublicCustomerBalanceController;
use App\Http\Controllers\Web\PublicStorageController;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/** Public disk uploads (DuitNow QR). Not `/storage/*` — Laravel registers its own `storage.local` route there. */
Route::get('/media/{path}', PublicStorageController::class)->where('path', '.*');

/** Old API URLs used `/storage/duitnow/...` before `/media/` — redirect so existing installs recover after deploy. */
Route::get('/storage/duitnow/{rest}', function (string $rest) {
    $path = 'duitnow/'.$rest;

    return redirect('/media/'.$path, 301);
})->where('rest', '.*');

Route::get('/v/{token}', PublicCustomerBalanceController::class)
    ->where('token', '[A-Za-z0-9]{40,128}');

/**
 * HTTPS OAuth redirect bridge for Expo / React Native Google Sign-In.
 * Google Console "Authorized redirect URIs" must include this URL (same host as APP_URL / API hostname).
 */
Route::get('/auth/google/expo-bridge', function () {
    return view('google.oauth_expo_bridge');
})->name('google.expo_oauth_bridge');

/**
 * Mobile/API users have no web session; Laravel's EmailVerificationRequest calls $this->user() and 500s when null.
 * Signed URL + explicit user lookup matches the link we send from SendVerificationEmailMailgunAction.
 */
Route::get('/email/verify/{id}/{hash}', function (int $id, string $hash) {
    $user = User::query()->find($id);

    if ($user === null) {
        abort(404);
    }

    if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
        abort(403);
    }

    if (! $user->hasVerifiedEmail()) {
        $user->markEmailAsVerified();
        event(new Verified($user));
    }

    $target = config('bakimate.email_verified_redirect_url') ?? 'bakimate://verify-email?verified=1';

    return redirect()->away($target);
})->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
