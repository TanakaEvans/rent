<?php

namespace App\Console\Commands;

use App\Services\SubscriptionService;
use Illuminate\Console\Command;

class SubscriptionExpireCommand extends Command
{
    protected $signature = 'subscriptions:expire';

    protected $description = 'Move subscriptions past their billing cycle into grace, and grace subscriptions past their deadline into suspension.';

    public function handle(SubscriptionService $subscriptions): int
    {
        $subscriptions->runExpiryCheck();

        $this->info('Subscription expiry check complete.');

        return self::SUCCESS;
    }
}