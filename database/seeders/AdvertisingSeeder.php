<?php

namespace Database\Seeders;

use App\Models\AdPackage;
use App\Models\AdPlacement;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdvertisingSeeder extends Seeder
{
    /**
     * Seed the ad package catalogue and demo placements (Module 13, Wave 4
     * slice 6). Demo placements are direct rows — no service side-effects, so
     * re-seeding stays notification-silent and test-stable. Two listings are
     * actively promoted (their seeded `featured` flag is placement-backed) and
     * one order sits `reserved` so the admin approval queue has demo content.
     */
    public function run(): void
    {
        $packages = [
            [
                'code' => 'featured-property',
                'name' => 'Featured Property',
                'placement_type' => 'featured',
                'price' => 10.00,
                'duration_days' => 30,
                'description' => 'Bump a listing above organic results with the Featured badge.',
            ],
            [
                'code' => 'top-placement',
                'name' => 'Top Placement',
                'placement_type' => 'top',
                'price' => 25.00,
                'duration_days' => 14,
                'description' => 'Pinned at the very top of the marketplace for two weeks.',
            ],
            [
                'code' => 'homepage-promotion',
                'name' => 'Homepage Promotion',
                'placement_type' => 'homepage',
                'price' => 40.00,
                'duration_days' => 7,
                'description' => 'Lead spot in the homepage featured rail for a week.',
            ],
            [
                'code' => 'premium-badge',
                'name' => 'Premium Property Badge',
                'placement_type' => 'premium_badge',
                'price' => 15.00,
                'duration_days' => 30,
                'description' => 'Premium marker that signals a high-quality, verified home.',
            ],
        ];

        foreach ($packages as $package) {
            AdPackage::updateOrCreate(
                ['code' => $package['code']],
                $package
            );
        }

        $owner = User::where('username', 'owner')->first();
        if (! $owner) {
            return;
        }

        $active = [
            'Modern 3 Bedroom House in Borrowdale' => 'featured-property',
            'Townhouse near Bulawayo CBD' => 'featured-property',
        ];

        foreach ($active as $title => $code) {
            $property = Property::where('title', $title)->first();
            if (! $property || AdPlacement::where('property_id', $property->id)->exists()) {
                continue;
            }

            $package = AdPackage::where('code', $code)->first();

            AdPlacement::create([
                'property_id' => $property->id,
                'owner_id' => $owner->id,
                'package_id' => $package->id,
                'amount' => $package->price,
                'status' => 'active',
                'starts_at' => now()->subDays(5),
                'ends_at' => now()->addDays(25),
                'paid_at' => now()->subDays(5),
                'credit_amount' => '0.00',
            ]);
        }

        $pending = Property::where('title', '2 Bedroom Flat with Generator Backup')->first();
        $pendingPackage = AdPackage::where('code', 'top-placement')->first();
        if ($pending && $pendingPackage && ! AdPlacement::where('property_id', $pending->id)->exists()) {
            AdPlacement::create([
                'property_id' => $pending->id,
                'owner_id' => $owner->id,
                'package_id' => $pendingPackage->id,
                'amount' => $pendingPackage->price,
                'status' => 'reserved',
                'credit_amount' => '0.00',
            ]);
        }
    }
}