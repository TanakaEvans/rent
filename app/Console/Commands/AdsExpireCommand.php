<?php

namespace App\Console\Commands;

use App\Services\AdPlacementService;
use Illuminate\Console\Command;

class AdsExpireCommand extends Command
{
    protected $signature = 'ads:expire';

    protected $description = 'Expire advertising placement windows whose end date has passed (Module 13, NFR-01).';

    public function handle(AdPlacementService $advertisements): int
    {
        $expired = $advertisements->expireDue();

        if ($expired > 0) {
            $this->info("Expired {$expired} advertising placement(s).");
        }

        return self::SUCCESS;
    }
}