<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessSubscriptionWebhookPayload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SubscriptionWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        if ($this->revenueCatAuthorizationInvalid($request)) {
            Log::warning('subscription.webhook.rejected.auth');

            return response('Forbidden.', 403);
        }

        /** @var array<string, mixed> $body */
        $body = $request->all();
        ProcessSubscriptionWebhookPayload::dispatch($body);

        return response('', 200);
    }

    private function revenueCatAuthorizationInvalid(Request $request): bool
    {
        $secret = trim((string) config('services.revenuecat.webhook_secret'));

        if ($secret === '') {
            Log::notice('REVENUECAT_WEBHOOK_SECRET is empty — subscription webhooks are not authenticated.');

            return false;
        }

        $given = trim((string) $request->header('Authorization', ''));

        foreach ([$secret, 'Bearer '.$secret] as $expected) {
            if (hash_equals($expected, $given)) {
                return false;
            }
        }

        if (stripos($given, 'bearer ') === 0 && hash_equals($secret, trim(substr($given, 7)))) {
            return false;
        }

        /** RC lets you paste a full `Authorization:` value in the dashboard; accept raw secret without prefix. */

        return true;
    }
}
