<?php

namespace App\Jobs;

use App\Models\Shop;
use App\Services\RevenueCatWebhookProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Updates {@see Shop::subscription_expires_at} from RevenueCat webhooks
 * (App Store + Google Play / PLAY_STORE subscriptions flow through RevenueCat).
 */
class ProcessSubscriptionWebhookPayload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public array $payload)
    {
        $this->onQueue('default');
    }

    public function handle(RevenueCatWebhookProcessor $processor): void
    {
        $processor->processWebhookPayload($this->payload);
    }
}
