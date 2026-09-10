<?php

namespace Database\Seeders;

use App\Models\Contractor;
use App\Models\MaintenanceAction;
use App\Models\MaintenanceRequest;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Seeder;

class MaintenanceSeeder extends Seeder
{
    /**
     * Demo maintenance rows for all three parties (Module 10 + 11). Direct rows
     * only — no service side-effects and no notifications, so seeding stays
     * silent and idempotent (keyed by request_no).
     *
     * MR-00001 is a normal request the owner has already assigned to a verified
     * contractor (with an approved quote) for the contractor + owner views;
     * MR-00002 is an emergency past its SLA that sits in the staff
     * escalation queue until acknowledged.
     */
    public function run(): void
    {
        $tenant = User::where('username', 'tenant')->first();
        $owner = User::where('username', 'owner')->first();
        $property = Property::where('title', '4 Bedroom Family Home in Kumalo')->first();

        if (! $tenant || ! $owner || ! $property) {
            return;
        }

        $assigned = MaintenanceRequest::firstOrCreate(
            ['request_no' => 'MR-2026-00001'],
            [
                'property_id' => $property->id,
                'tenant_id' => $tenant->id,
                'category' => 'plumbing',
                'priority' => 'medium',
                'title' => 'Kitchen tap dripping',
                'description' => 'The kitchen tap has been dripping steadily for two days and the basin plughole leaks when the tap runs.',
                'status' => 'reported',
                'sla_due_at' => now()->addHours(96),
            ]
        );

        $contractor = Contractor::where('business_name', 'Bulawayo Plumbing Co.')->first();
        if ($contractor) {
            $assigned->update([
                'status' => 'assigned',
                'approved_quote' => '85.00',
                'assigned_contractor_id' => $contractor->id,
            ]);
        }
        MaintenanceAction::firstOrCreate(
            ['request_id' => $assigned->id, 'action' => 'reported'],
            ['actor_id' => $tenant->id]
        );
        MaintenanceAction::firstOrCreate(
            ['request_id' => $assigned->id, 'action' => 'assigned'],
            ['actor_id' => $owner->id, 'notes' => '$85.00 — Bulawayo Plumbing Co.']
        );

        $emergency = MaintenanceRequest::firstOrCreate(
            ['request_no' => 'MR-2026-00002'],
            [
                'property_id' => $property->id,
                'tenant_id' => $tenant->id,
                'category' => 'safety',
                'priority' => 'emergency',
                'title' => 'Sparks from the air conditioning unit',
                'description' => 'The AC unit in the lounge sparked twice this morning and smells of burning plastic. Switching it off now.',
                'status' => 'reported',
                'sla_due_at' => now()->subHours(2),
                'escalated_at' => now()->subHour(),
            ]
        );
        MaintenanceAction::firstOrCreate(
            ['request_id' => $emergency->id, 'action' => 'reported'],
            ['actor_id' => $tenant->id]
        );
        MaintenanceAction::firstOrCreate(
            ['request_id' => $emergency->id, 'action' => 'escalated'],
            ['actor_id' => null, 'notes' => 'SLA breached — auto-escalated']
        );
    }
}