<?php

namespace Database\Seeders;

use App\Models\Document;
use App\Models\Property;
use App\Models\PropertyFavourite;
use App\Models\PropertyImage;
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
                'status' => 'occupied',
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

        // Map/coordinates + availability variety per listing, keyed by title.
        // Some listings are available now (null), others open on a future
        // date so the marketplace exercises both states.
        $locationOverrides = [
            'Modern 3 Bedroom House in Borrowdale' => ['latitude' => -17.7833, 'longitude' => 31.0833, 'available_from' => null],
            '2 Bedroom Flat with Generator Backup' => ['latitude' => -17.8064, 'longitude' => 31.0208, 'available_from' => null],
            'Townhouse near Bulawayo CBD' => ['latitude' => -20.1650, 'longitude' => 28.6050, 'available_from' => now()->addDays(14)],
            'Bachelor Room in Msasa' => ['latitude' => -17.8500, 'longitude' => 31.1167, 'available_from' => null],
            '4 Bedroom Family Home in Kumalo' => ['latitude' => -20.1740, 'longitude' => 28.5740, 'available_from' => now()->addDays(30)],
            'Office Space in Avondale' => ['latitude' => -17.8000, 'longitude' => 31.0333, 'available_from' => now()->addDays(7)],
        ];

        foreach ($properties as $data) {
            $overrides = $locationOverrides[$data['title']] ?? [];
            Property::updateOrCreate(
                ['title' => $data['title']],
                array_merge(
                    ['owner_id' => $owner->id, 'expires_at' => now()->addDays(60)],
                    $data,
                    $overrides
                )
            );
        }

        // Photo gallery for every listing, so the marketplace and detail
        // page render full carousels. Uses local SVG placeholders until the
        // owner uploads real photos; the first image becomes the cover.
        $imageFiles = ['house-1.svg', 'apartment-1.svg', 'townhouse-1.svg', 'room-1.svg', 'house-2.svg', 'office-1.svg'];
        foreach (Property::all() as $property) {
            $paths = [];
            foreach (range(0, 3) as $sort) {
                $file = $imageFiles[($property->id + $sort) % count($imageFiles)];
                $path = "/uploads/homes/{$file}";
                PropertyImage::firstOrCreate(
                    ['property_id' => $property->id, 'path' => $path],
                    ['caption' => 'Demo ' . ucfirst($property->property_type) . ' photo', 'sort_order' => $sort]
                );
                $paths[] = $path;
            }
            $property->update(['cover_image' => $paths[0]]);
        }

        // Marketplace analytics demo (single guard so re-seeding never
        // duplicates): every listing earns several dated views so detail
        // analytics, "recently viewed" and the Popular badge all have data.
        if (! \App\Models\PropertyView::exists()) {
            foreach (Property::listed()->get() as $rank => $property) {
                foreach (range(0, 4 + $rank) as $day) {
                    \App\Models\PropertyView::create([
                        'property_id' => $property->id,
                        'user_id' => null,
                        'ip' => '127.0.0.1',
                        'viewed_at' => now()->subDays($day)->setTime(10, 0),
                    ]);
                }
            }
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

            // Saved search + a marketplace report so the tenant and the
            // admin moderation queue both have demo content.
            \App\Models\SavedSearch::firstOrCreate(
                ['user_id' => $tenant->id, 'name' => '3-bed Harare under $1,000'],
                [
                    'criteria' => ['property_type' => 'house', 'city' => 'Harare', 'bedrooms' => 3, 'max_price' => 1000, 'status' => 'available'],
                    'notify' => true,
                ]
            );

            $reportTarget = \App\Models\Property::where('title', 'Bachelor Room in Msasa')->first();
            if ($reportTarget) {
                \App\Models\Report::firstOrCreate(
                    ['reporter_id' => $tenant->id, 'subject_id' => $reportTarget->id],
                    [
                        'subject_type' => 'property',
                        'category' => 'incorrect_information',
                        'description' => 'Demo report — the rent shown on this listing looks stale.',
                        'status' => 'open',
                        'priority' => 'medium',
                    ]
                );
            }

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

                // Demo renewal chain: an active lease on the occupied Kumalo
                // property plus its renewal draft, so the owner list exercises
                // the renew flow and the tenant sees both agreements (direct
                // rows only — no service side-effects).
                $occupiedTarget = \App\Models\Property::where('title', '4 Bedroom Family Home in Kumalo')->first();
                if ($occupiedTarget) {
                    $occupiedTarget->update(['status' => 'occupied']);
                    $activeLease = \App\Models\Lease::firstOrCreate(
                        ['lease_no' => 'LSE-DEMO-2026-003'],
                        [
                            'property_id' => $occupiedTarget->id,
                            'tenant_id' => $tenant->id,
                            'application_id' => null,
                            'start_date' => now()->subMonths(10)->toDateString(),
                            'end_date' => now()->addDays(60)->toDateString(),
                            'rent_amount' => $occupiedTarget->price,
                            'deposit_amount' => $occupiedTarget->deposit ?? 0,
                            'payment_terms' => ['frequency' => 'monthly', 'due_day' => 1, 'description' => 'Rent is due on the 1st of each month.'],
                            'status' => 'active',
                            'clause_version' => 1,
                        ]
                    );

                    \App\Models\Lease::firstOrCreate(
                        ['lease_no' => 'LSE-DEMO-2026-002'],
                        [
                            'property_id' => $occupiedTarget->id,
                            'tenant_id' => $tenant->id,
                            'application_id' => null,
                            'renewed_from_id' => $activeLease->id,
                            'start_date' => now()->addDays(60)->toDateString(),
                            'end_date' => now()->addDays(60)->addYear()->toDateString(),
                            'rent_amount' => $occupiedTarget->price,
                            'deposit_amount' => $occupiedTarget->deposit ?? 0,
                            'payment_terms' => ['frequency' => 'monthly', 'due_day' => 1, 'description' => 'Rent is due on the 1st of each month.'],
                            'status' => 'draft',
                            'clause_version' => 1,
                        ]
                    );

                    // Stored agreement for the active lease (M20-lite), so both
                    // parties can demo the document store. Rendered through the
                    // service so the snapshot matches runtime format exactly;
                    // guarded so re-seeding never bumps the version.
                    if (! Document::where('lease_id', $activeLease->id)->exists()) {
                        app(\App\Services\DocumentService::class)->storeLeaseAgreement($activeLease);
                    }

                    // Rent schedule for the active lease (M9, Wave 4 slice 3):
                    // generated through the service so the demo rows match
                    // runtime exactly (guarded — re-seeding never re-bills),
                    // then run through the lifecycle so past periods read
                    // overdue, the live period reads due and the date reminder
                    // resolves against the real configuration.
                    if (! \App\Models\RentSchedule::where('lease_id', $activeLease->id)->exists()) {
                        app(\App\Services\RentService::class)->generateFor($activeLease);
                        app(\App\Services\RentService::class)->runInvoiceLifecycle(false);
                    }

                    // Demo payment (M9, Wave 4 slice 4): the tenant records a cash payment for
                    // the live (due) invoice. The $1,000.00 invoice clears the
                    // $500.00 approval threshold, so the payment sits `pending`
                    // and staff can demo the approval queue — approve settles
                    // the invoice + issues a receipt, reject leaves it owing.
                    // (Bank/mobile would need a proof-of-payment upload, which a
                    // seeder cannot attach, so cash keeps the demo file-free.)
                    // Guarded: re-seeding never duplicates the payment.
                    $dueInvoice = \App\Models\RentInvoice::where('lease_id', $activeLease->id)
                        ->where('status', 'due')
                        ->orderBy('period_start')
                        ->first();

                    if ($dueInvoice && ! \App\Models\Payment::where('invoice_id', $dueInvoice->id)->exists()) {
                        app(\App\Services\PaymentService::class)->recordPayment($tenant, $dueInvoice, [
                            'amount' => $dueInvoice->amount,
                            'method' => 'cash',
                            'reference' => 'Cash at branch — live demo payment',
                        ]);
                    }
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