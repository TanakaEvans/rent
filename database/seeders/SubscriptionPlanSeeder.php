<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Seed the plan catalog and give the demo owner a Business subscription so
     * listing quota never blocks the demo data.
     */
    public function run(): void
    {
        $plans = [
            ['name' => 'Free', 'listing_limit' => 1, 'featured_slots' => 0, 'support_tier' => 'standard', 'analytics_enabled' => false, 'price' => 0],
            ['name' => 'Basic', 'listing_limit' => 5, 'featured_slots' => 1, 'support_tier' => 'standard', 'analytics_enabled' => false, 'price' => 5],
            ['name' => 'Professional', 'listing_limit' => 20, 'featured_slots' => 3, 'support_tier' => 'priority', 'analytics_enabled' => true, 'price' => 15],
            ['name' => 'Business', 'listing_limit' => null, 'featured_slots' => 10, 'support_tier' => 'dedicated', 'analytics_enabled' => true, 'price' => 40],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(
                ['name' => $plan['name']],
                $plan + ['billing_cycle' => 'monthly', 'status' => 'active']
            );
        }

        $owner = User::where('email', 'owner@dzimba.local')->first();
        if (! $owner) {
            return;
        }

        $business = SubscriptionPlan::where('name', 'Business')->first();

        if ($owner->subscriptions()->doesntExist()) {
            $subscription = $owner->subscriptions()->create([
                'plan_id' => $business->id,
                'status' => 'active',
                'starts_at' => now(),
                'ends_at' => now()->addDays(30),
                'cycle' => 'monthly',
                'details' => null,
            ]);

            $subscription->recordEvent('subscribed', ['plan' => $business->name]);

            $subscription->invoices()->create([
                'amount' => $business->price,
                'status' => 'paid',
                'invoice_no' => 'SUB-' . now()->year . '-DEMO',
                'receipt_no' => 'RCT-SUB-DEMO',
                'paid_at' => now(),
                'details' => ['event' => 'subscribed'],
            ]);
        }
    }
}