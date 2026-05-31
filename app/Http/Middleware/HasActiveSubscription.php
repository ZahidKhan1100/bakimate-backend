<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HasActiveSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        /**
         * Mobile app replays rows queued offline with X-Bakimate-Offline-Sync.
         * Recording still requires premium when online; only the outbox flush uses this header.
         */
        if ($request->header('X-Bakimate-Offline-Sync') !== '1') {
            $shop = $user->shops()->first();
            if (! $shop || ! $shop->subscriptionActive()) {
                return response()->json([
                    'message' => 'Active subscription required for this action.',
                ], 403);
            }
        }

        return $next($request);
    }
}
