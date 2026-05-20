<?php

namespace App\Http\Controllers\Api;

use App\Actions\Auth\AuthenticateAppleUserAction;
use App\Actions\Auth\AuthenticateGoogleUserAction;
use App\Actions\Auth\DeleteUserAccountAction;
use App\Actions\Auth\RegisterEmailUserAction;
use App\Actions\Auth\SendVerificationEmailMailgunAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\AppleLoginRequest;
use App\Http\Requests\EmailLoginRequest;
use App\Http\Requests\EmailRegisterRequest;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\GoogleLoginRequest;
use App\Http\Requests\ResetPasswordRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class AuthController extends Controller
{
    public function __construct(
        private readonly SendVerificationEmailMailgunAction $sendVerificationEmailMailgun,
    ) {}

    public function google(GoogleLoginRequest $request, AuthenticateGoogleUserAction $action): JsonResponse
    {
        try {
            $user = $action->execute($request->validated('id_token'));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 401);
        }

        return $this->authResponse($user);
    }

    public function apple(AppleLoginRequest $request, AuthenticateAppleUserAction $action): JsonResponse
    {
        try {
            $user = $action->execute(
                $request->validated('id_token'),
                $request->validated('full_name'),
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 401);
        }

        return $this->authResponse($user);
    }

    /**
     * Register with email + password (creates default shop). Sends verification email; no API token until verified.
     */
    public function register(EmailRegisterRequest $request, RegisterEmailUserAction $action): JsonResponse
    {
        $user = $action->execute($request->validated());
        $this->sendVerificationEmailMailgun->execute($user);

        return $this->registrationPendingResponse($user);
    }

    public function login(EmailLoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();
        $user = User::query()->where('email', $credentials['email'])->first();

        if ($user === null || $user->password === null) {
            return response()->json([
                'message' => 'Invalid email or password. If you created this account with Google or Apple, sign in with that provider.',
            ], 401);
        }

        if (! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid email or password.',
            ], 401);
        }

        if (! $user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Please verify your email address first. Check your inbox for the verification link.',
                'email_verified' => false,
            ], 403);
        }

        return $this->authResponse($user);
    }

    /**
     * Habimate-style poll: returns a Sanctum token once the address is verified (same shape as login).
     */
    public function checkEmailVerified(ForgotPasswordRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->validated('email'))->first();

        if ($user === null || ! $user->hasVerifiedEmail()) {
            return response()->json(['email_verified' => false]);
        }

        return $this->authResponse($user);
    }

    /**
     * Re-send the signed verification link (throttled). Generic message if the user does not exist.
     */
    public function resendVerification(ForgotPasswordRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->validated('email'))->first();

        if ($user !== null && ! $user->hasVerifiedEmail()) {
            $this->sendVerificationEmailMailgun->execute($user);
        }

        return response()->json([
            'message' => 'If that account exists and still needs verification, a new email was sent.',
        ]);
    }

    /**
     * Sends a password reset email. Response is always generic to avoid email enumeration.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink($request->only('email'));

        return response()->json([
            'message' => 'If an account exists for that email, password reset instructions were sent.',
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill(['password' => $password])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'This reset link is invalid or has expired. Request a new reset from the app.',
            ], 422);
        }

        return response()->json([
            'message' => 'Password has been reset. You can sign in now.',
        ]);
    }

    /**
     * Dev/demo helper: returns a token for the seeded demo account when enabled.
     */
    public function demo(): JsonResponse
    {
        if (! config('bakimate.demo_login_enabled')) {
            return response()->json([
                'message' => 'Demo login is disabled. For local dev set APP_ENV=local (default) or DEMO_LOGIN_ENABLED=true in .env. Never enable on public production APIs.',
            ], 403);
        }

        $user = User::query()->where('email', 'demo@bakimate.test')->first();

        if (! $user) {
            return response()->json([
                'message' => 'Demo user missing. Run: php artisan migrate --seed (creates demo@bakimate.test).',
            ], 503);
        }

        return $this->authResponse($user, 'mobile-demo');
    }

    /**
     * Permanently delete the authenticated user's account (owned shops, ledger rows via FK cascade).
     */
    public function destroyAccount(Request $request, DeleteUserAccountAction $action): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        try {
            $action->execute($user);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Your account and shop data were permanently deleted.',
        ]);
    }

    private function registrationPendingResponse(User $user): JsonResponse
    {
        return response()->json([
            'message' => 'We sent a verification link to your email. Open it, then return here to continue.',
            'verification_required' => true,
            'user' => $this->userJson($user),
        ], 201);
    }

    /**
     * @return array{id: int, name: string, email: string, email_verified_at: string|null}
     */
    private function userJson(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
        ];
    }

    private function authResponse(User $user, string $tokenName = 'mobile'): JsonResponse
    {
        $token = $user->createToken($tokenName)->plainTextToken;
        $shop = $user->shops()->first();

        return response()->json([
            'token' => $token,
            'user' => $this->userJson($user),
            'shop' => $shop !== null ? ShopProfileController::serializeShop($shop) : null,
        ]);
    }
}
