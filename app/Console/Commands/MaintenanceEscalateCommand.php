<?php

namespace App\Console\Commands;

use App\Services\MaintenanceService;
use Illuminate\Console\Command;

class MaintenanceEscalateCommand extends Command
{
    protected $signature = 'maintenance:escalate';

    protected $description = 'Escalate maintenance requests past their first-response SLA to staff (Module 10, NFR-01).';

    public function handle(MaintenanceService $maintenance): int
    {
        $escalated = $maintenance->escalateDue();

        if ($escalated > 0) {
            $this->info("Escalated {$escalated} maintenance request(s) to staff.");
        }

        return self::SUCCESS;
    }
}