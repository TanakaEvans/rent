<?php

namespace Database\Seeders;

use App\Models\Contractor;
use App\Models\ContractorTrade;
use App\Models\User;
use Illuminate\Database\Seeder;

class ContractorSeeder extends Seeder
{
    /**
     * Demo contractor registry (Module 11, Wave 5 slice 2). Direct rows only —
     * no service side-effects, so seeding stays silent and idempotent (keyed
     * by business_name).
     *
     * Two verified profiles demos the owner assign dropdown; a vetting profile
     * demos the admin verification pipeline. The verified demo is linked to
     * the contractor@dzimba.local login so assignment notifications land in a
     * real inbox.
     */
    public function run(): void
    {
        $contractorUser = User::where('username', 'contractor')->first();

        $plumbing = Contractor::firstOrCreate(
            ['business_name' => 'Bulawayo Plumbing Co.'],
            [
                'user_id' => $contractorUser?->id,
                'contact' => '0712 345 678 · bulawayoplumbing@example.com',
                'service_area' => ['Bulawayo'],
                'status' => 'verified',
                'rating_avg' => 4.80,
                'jobs_completed' => 12,
                'verified_at' => now(),
            ]
        );
        foreach ([['trade' => 'Plumbing', 'rate' => 25.00], ['trade' => 'Electrical', 'rate' => 30.00]] as $trade) {
            ContractorTrade::firstOrCreate(
                ['contractor_id' => $plumbing->id, 'trade' => $trade['trade']],
                ['rate' => $trade['rate']]
            );
        }

        $structural = Contractor::firstOrCreate(
            ['business_name' => 'Mura Building Services'],
            [
                'user_id' => null,
                'contact' => '0771 234 890 · murasurendu@example.com',
                'service_area' => ['Bulawayo'],
                'status' => 'verified',
                'rating_avg' => 4.60,
                'jobs_completed' => 8,
                'verified_at' => now(),
            ]
        );
        foreach ([['trade' => 'Structural', 'rate' => 40.00], ['trade' => 'Roofing', 'rate' => null]] as $trade) {
            ContractorTrade::firstOrCreate(
                ['contractor_id' => $structural->id, 'trade' => $trade['trade']],
                ['rate' => $trade['rate']]
            );
        }

        Contractor::firstOrCreate(
            ['business_name' => 'CleanFlow Pest Solutions'],
            [
                'user_id' => null,
                'contact' => '0780 111 222 · pestcontrol@example.com',
                'service_area' => ['Bulawayo', 'Gweru'],
                'status' => 'vetting',
                'rating_avg' => 0,
                'jobs_completed' => 0,
                'verified_at' => null,
            ]
        );
    }
}