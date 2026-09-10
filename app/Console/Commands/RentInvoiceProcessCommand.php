<?php

namespace App\Console\Commands;

use App\Services\RentService;
use Illuminate\Console\Command;

class RentInvoiceProcessCommand extends Command
{
    protected $signature = 'rent:process';

    protected $description = 'Advance rent invoices through the lifecycle (draft -> due -> overdue) and send due-date reminders.';

    public function handle(RentService $rent): int
    {
        $rent->runInvoiceLifecycle();

        $this->info('Rent invoice lifecycle check complete.');

        return self::SUCCESS;
    }
}