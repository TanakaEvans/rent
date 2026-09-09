<?php

namespace Database\Seeders;

use App\Models\Property;
use App\Models\PropertyFavourite;
use App\Models\RentalApplication;
use App\Models\User;
use Illuminate\Database\Seeder;

class PropertySeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::where('username', 'owner')->first();
        if (! $owner) {
            return;
        }

        $tenant = User::where('username', 'tenant')->first();

        $properties = [
            [
                'title' => 'Modern 3 Bedroom House in Borrowdale',
                'building_size' => 260,
                'land_size' => 1200,
                'zone' => 'Harare North',
                'description' => 'Spacious family home with a large garden, double carport and borehole. Quiet and secure neighbourhood close to schools and shops.',
                'property_type' => 'house',
                'bedrooms' => 3,
                'bathrooms' => 2,
                'price' => 850,
                'deposit' => 850,
                'furnished' => false,
                'status' => 'available',
                'suburb' => 'Borrowdale',
                'city' => 'Harare',
                'address' => '14 Borrowdale Road',
                'amenities' => ['borehole', 'carport', 'garden', 'security'],
                'featured' => true,
                'verified' => true,
            ],
            [
                'title' => '2 Bedroom Flat with Generator Backup',
                'building_size' => 82,
                'zone' => 'CBD',
                'description' => 'Neat and secure ground floor flat. Fully tiled, fitted kitchen with rental stove and fridge. Ideal for a small family or young professionals.',
                'property_type' => 'flat',
                'bedrooms' => 2,
                'bathrooms' => 1,
                'price' => 450,
                'deposit' => 450,
                'furnished' => true,
                'status' => 'available',
                'suburb' => 'Gunhill',
                'city' => 'Harare',
                'address' => '7 Lomagundi Road',
                'amenities' => ['generator', 'water_tank', 'security'],
                'featured' => false,
                'verified' => true,
            ],
            [
                'title' => 'Townhouse near Bulawayo CBD',
                'building_size' => 190,
                'land_size' => 340,
                'zone' => 'Bulawayo Central',
                'description' => 'Beautiful townhouse in a secure complex, three bedrooms with fitted wardrobes, double automated garage. Close to all amenities.',
                'property_type' => 'townhouse',
                'bedrooms' => 3,
                'bathrooms' => 2,
                'price' => 650,
                'deposit' => 650,
                'furnished' => false,
                'status' => 'available',
                'suburb' => 'Killarney',
                'city' => 'Bulawayo',
                'address' => '45 Killarney Road',
                'amenities' => ['automated_gate', 'double_garage', 'security'],
                'featured' => true,
                'verified' => true,
            ],
            [
                'title' => 'Bachelor Room in Msasa',
                'description' => 'Self-contained bachelor room with prepaid water and electricity meters. Suitable for a single working professional.',
                'property_type' => 'room',
                'bedrooms' => 1,
                'bathrooms' => 1,
                'price' => 150,
                'deposit' => 150,
                'furnished' => true,
                'status' => 'available',
                'suburb' => 'Msasa',
                'city' => 'Harare',
                'address' => '11 Business Park',
                'amenities' => ['prepaid_utilities', 'secure_parking'],
                'featured' => false,
                'verified' => false,
            ],
            [
                'title' => '4 Bedroom Family Home in Kumalo',
                'description' => 'Large stand with a well maintained main house, servants quarters and a borehole. Ideal for a large family.',
                'property_type' => 'house',
                'bedrooms' => 4,
                'bathrooms' => 3,
                'price' => 1000,
                'deposit' => 1000,
                'furnished' => false,
                'status' => 'reserved',
                'suburb' => 'Kumalo',
                'city' => 'Bulawayo',
                'address' => '23 Matsheumhlophe Road',
                'amenities' => ['borehole', 'swimming_pool', 'servants_quarters'],
                'featured' => false,
                'verified' => true,
            ],
            [
                'title' => 'Office Space in Avondale',
                'description' => 'Fitted office space with air conditioning, backup power and ample parking. Perfect for a small business.',
                'property_type' => 'commercial',
                'bedrooms' => 0,
                'bathrooms' => 2,
                'price' => 1200,
                'deposit' => 1200,
                'furnished' => true,
                'status' => 'available',
                'suburb' => 'Avondale',
                'city' => 'Harare',
                'address' => '6 Westgate Mall',
                'amenities' => ['backup_power', 'aircon', 'parking'],
                'featured' => false,
                'verified' => true,
            ],
        ];

        foreach ($properties as $data) {
            Property::firstOrCreate(
                ['title' => $data['title']],
                array_merge(['owner_id' => $owner->id, 'available_from' => now()->addDays(14)], $data)
            );
        }

        // Demo favourited properties and an application for the tenant
        if ($tenant) {
            $available = Property::listed()->get();

            $available->take(2)->each(function ($property) use ($tenant) {
                PropertyFavourite::firstOrCreate([
                    'user_id' => $tenant->id,
                    'property_id' => $property->id,
                ]);
            });

            if ($available->isNotEmpty()) {
                $demoApplication = RentalApplication::firstOrCreate(
                    ['applicant_id' => $tenant->id, 'property_id' => $available->first()->id],
                    [
                        'message' => 'Hi, I am very interested in this property. I am a young professional looking for a long-term lease.',
                        'status' => 'pending',
                    ]
                );
                $owner->notify(new \App\Notifications\NewApplicationNotification($demoApplication));

                // Demo lease: an approved application on a second property plus its draft
                // lease, so the tenant sees an agreement awaiting review without mutating
                // the property status (the real reservation handover is covered by tests).
                $leaseTarget = $available->skip(2)->first();
                if ($leaseTarget && $leaseTarget->id !== $demoApplication->property_id) {
                    $leaseApplication = \App\Models\RentalApplication::firstOrCreate(
                        ['applicant_id' => $tenant->id, 'property_id' => $leaseTarget->id],
                        [
                            'message' => 'Please consider my application, I would love to secure this as my new home.',
                            'status' => 'approved',
                            'reviewed_by' => $owner->id,
                        ]
                    );
                    $owner->notify(new \App\Notifications\NewApplicationNotification($leaseApplication));

                    \App\Models\Lease::firstOrCreate(
                        ['lease_no' => 'LSE-DEMO-2026-001'],
                        [
                            'property_id' => $leaseTarget->id,
                            'tenant_id' => $tenant->id,
                            'application_id' => $leaseApplication->id,
                            'start_date' => now()->toDateString(),
                            'end_date' => now()->addYear()->toDateString(),
                            'rent_amount' => $leaseTarget->price,
                            'deposit_amount' => $leaseTarget->deposit ?? 0,
                            'payment_terms' => ['frequency' => 'monthly', 'due_day' => 1, 'description' => 'Rent is due on the 1st of each month.'],
                            'status' => 'draft',
                            'clause_version' => 1,
                        ]
                    );
                }

                $enquiryTarget = $available->skip(1)->first();
                if ($enquiryTarget) {
                    $demoEnquiry = \App\Models\Enquiry::firstOrCreate(
                        ['tenant_id' => $tenant->id, 'property_id' => $enquiryTarget->id],
                        [
                            'message' => 'Hello! Is this property still available for a move-in next month? Also, are water and refuse charges included in the rent?',
                            'phone' => '+263 77 123 4567',
                            'status' => 'new',
                        ]
                    );
                    $owner->notify(new \App\Notifications\NewEnquiryNotification($demoEnquiry));
                }

                $slotTarget = $available->first();
                if ($slotTarget) {
                    foreach ([-1, 3, 6] as $offset) {
                        \App\Models\ViewingSlot::firstOrCreate(
                            ['property_id' => $slotTarget->id, 'starts_at' => now()->addDays($offset)->setTime(10, 0)],
                            [
                                'owner_id' => $owner->id,
                                'ends_at' => now()->addDays($offset)->setTime(11, 0),
                                'status' => 'available',
                            ]
                        );
                    }

                    $demoSlot = \App\Models\ViewingSlot::where('property_id', $slotTarget->id)
                        ->where('starts_at', '>', now())
                        ->orderBy('starts_at')
                        ->first();
                    if ($demoSlot) {
                        $demoBooking = \App\Models\ViewingRequest::firstOrCreate(
                            ['tenant_id' => $tenant->id, 'slot_id' => $demoSlot->id],
                            [
                                'property_id' => $slotTarget->id,
                                'request_message' => 'I would like to bring a friend along for a second opinion.',
                                'status' => 'requested',
                            ]
                        );
                        $owner->notify(new \App\Notifications\ViewingRequestedNotification($demoBooking));
                    }
                }
            }
        }
    }
}