<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExtendExistingTrialsCommand extends Command
{
    protected $signature = 'trial:extend-existing
                            {--extra-days=60 : Days to add on top of the current expiry (30 -> 90 day trial)}
                            {--tolerance-hours=6 : How close subscription_expires_at must be to created_at + 30 days to count as an untouched trial}
                            {--apply : Actually write changes (default is a dry run)}';

    protected $description = 'Extend subscription_expires_at for shops still on their original, untouched 30-day signup trial to 90 days';

    public function handle(): int
    {
        $extraDays = (int) $this->option('extra-days');
        $toleranceHours = (int) $this->option('tolerance-hours');

        $candidates = Shop::query()
            ->whereNotNull('subscription_expires_at')
            ->whereNotNull('created_at')
            ->whereRaw(
                'ABS(TIMESTAMPDIFF(MINUTE, subscription_expires_at, DATE_ADD(created_at, INTERVAL 30 DAY))) <= ?',
                [$toleranceHours * 60],
            )
            ->get(['id', 'name', 'created_at', 'subscription_expires_at']);

        if ($candidates->isEmpty()) {
            $this->info('No shops matched the untouched-trial heuristic. Nothing to do.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Found %d shop(s) whose subscription_expires_at is within %dh of created_at + 30 days (likely untouched trial).',
            $candidates->count(),
            $toleranceHours,
        ));

        $this->table(
            ['Shop ID', 'Name', 'Created At', 'Current Expiry', 'New Expiry'],
            $candidates->map(fn (Shop $shop) => [
                $shop->id,
                $shop->name,
                $shop->created_at->toDateTimeString(),
                $shop->subscription_expires_at->toDateTimeString(),
                $shop->subscription_expires_at->copy()->addDays($extraDays)->toDateTimeString(),
            ]),
        );

        if (! $this->option('apply')) {
            $this->warn('Dry run only — no changes written. Re-run with --apply to update these shops.');

            return self::SUCCESS;
        }

        if (! $this->confirm(sprintf('Apply +%d days to all %d shop(s) above?', $extraDays, $candidates->count()), false)) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($candidates, $extraDays): void {
            foreach ($candidates as $shop) {
                $shop->subscription_expires_at = $shop->subscription_expires_at->copy()->addDays($extraDays);
                $shop->save();
            }
        });

        $this->components->success(sprintf('Extended %d shop(s) by %d days.', $candidates->count(), $extraDays));

        return self::SUCCESS;
    }
}
