<?php

$demoExplicit = env('DEMO_LOGIN_ENABLED');

return [
    /**
     * When true, POST /api/auth/demo issues a Sanctum token for the seeded demo user.
     *
     * If DEMO_LOGIN_ENABLED is omitted from .env, it defaults to enabled only when APP_ENV is local,
     * so artisan serve works without extra config. Set DEMO_LOGIN_ENABLED=false to turn it off locally,
     * or DEMO_LOGIN_ENABLED=true to allow it on other environments (not recommended).
     */
    'demo_login_enabled' => $demoExplicit !== null && $demoExplicit !== ''
        ? filter_var($demoExplicit, FILTER_VALIDATE_BOOLEAN)
        : env('APP_ENV', 'production') === 'local',

    /**
     * Deep link used in password reset emails (must match Expo `scheme` + route).
     * Example: bakimate://reset-password?token=...&email=...
     */
    'password_reset_app_scheme' => env('PASSWORD_RESET_APP_SCHEME', 'bakimate'),

    /** After the user taps the link in the verification email (served by the API web route). */
    'email_verified_redirect_url' => env('EMAIL_VERIFIED_REDIRECT_URL', 'bakimate://verify-email?verified=1'),

    /**
     * Expo / native Google Sign-In OAuth bridge: Google's Web client only allows https redirect URIs.
     * The app sets redirect_uri to a route hitting this Laravel URL (see Route:: auth/google/expo-bridge).
     * This blade forwards search + hash to the app deep link below.
     */
    'google_expo_oauth_deep_link' => env('GOOGLE_EXPO_OAUTH_DEEP_LINK', 'bakimate://oauthredirect'),
];
