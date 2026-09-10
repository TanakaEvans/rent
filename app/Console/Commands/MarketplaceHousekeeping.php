<?php

namespace App\Console\Commands;

use App\Notifications\ListingExpiryReminderNotification;
use App\Services\ConfigurationService;
use App\Services\ListingLifecycleService;
use Illuminate\Console\Command;

/**
 * Daily marketplace housekeeping (Marketplace §40/§41):
 *  - expire listings past their validity window (config-driven)
 *  - remind owners before expiry per listings.validity_reminders (e.g. 14/7/1)
 */
class MarketplaceHousekeeping extends Command
{
    protected $signature = 'marketplace:housekeeping';

    protected $description = 'Expire overdue listings and send expiry reminders';

    public function handle(ListingLifecycleService $lifecycle, ConfigurationService $config): int
    {
        $expired = $lifecycle->expireDue();
        $this->info("Expired {$expired} listing(s).");

        $reminders = (array) $config->get('listings.validity_reminders', [14, 7, 1]);

        foreach ($reminders as $days) {
            $due = $lifecycle->dueSoon((int) $days);
            foreach ($due as $property) {
                $property->owner?->notify(new ListingExpiryReminderNotification($property, (int) $days));
                $this->line("Reminded owner about '{$property->title}' ({$property->expires_at?->diffInDays(now())} day(s) left).");
            }
        }

        return self::SUCCESS;
    }
}