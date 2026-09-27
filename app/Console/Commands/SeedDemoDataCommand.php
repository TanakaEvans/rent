<?php

namespace App\Console\Commands;

use App\Models\Location;
use App\Models\Role;
use App\Models\SubscriptionPlan;
use App\Models\TenantProfile;
use App\Support\DemoPhotos;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Generates a large, realistic data set for load and performance testing:
 * owners and tenants, thousands of listings with real photos spread over the
 * Zimbabwe location catalogue, and the marketplace activity around them
 * (favourites, views, enquiries, interests, viewings, applications, saved
 * searches, reports).
 *
 * Every generated account uses the @loadtest.zimrent.test email domain, so the
 * data set is easy to recognise and `--fresh` removes it completely (all
 * related rows cascade from the users). Rows are written with chunked bulk
 * inserts and respect the same rules the app enforces (one open enquiry and
 * one active application per tenant×property, one approved application per
 * property, listing counts within each owner's plan limit, accepted viewings
 * lock their slot).
 */
class SeedDemoDataCommand extends Command
{
    protected $signature = 'zimrent:seed-demo
        {--owners=600 : Property owners to create}
        {--tenants=5000 : Tenants to create}
        {--properties=6000 : Listings to create}
        {--seed=2026 : Random seed, so the data set is reproducible}
        {--fresh : Remove the previous load-test data set first}';

    protected $description = 'Generate thousands of realistic owners, tenants, listings (with photos) and activity for load testing';

    public const EMAIL_DOMAIN = 'loadtest.zimrent.test';

    private const CHUNK = 1000;

    private const FIRST_NAMES = [
        'Tendai', 'Tatenda', 'Rutendo', 'Farai', 'Tafadzwa', 'Chipo', 'Nyasha', 'Kudzai', 'Tinashe', 'Rumbidzai',
        'Takudzwa', 'Ropafadzo', 'Tapiwa', 'Vimbai', 'Munashe', 'Anesu', 'Tanaka', 'Chiedza', 'Fadzai', 'Kundai',
        'Sipho', 'Thandiwe', 'Nkosana', 'Sibusiso', 'Nomvula', 'Bongani', 'Zanele', 'Lindiwe', 'Mandla', 'Themba',
        'Blessing', 'Precious', 'Memory', 'Tawanda', 'Simbarashe', 'Panashe', 'Makanaka', 'Ruvimbo', 'Tsitsi', 'Shingai',
    ];

    private const LAST_NAMES = [
        'Moyo', 'Ncube', 'Sibanda', 'Dube', 'Mpofu', 'Ndlovu', 'Chikwanha', 'Mutasa', 'Chiweshe', 'Makoni',
        'Gumbo', 'Mhlanga', 'Nyathi', 'Mazarura', 'Chigumba', 'Marufu', 'Chirwa', 'Mandaza', 'Zvobgo', 'Hove',
        'Mapfumo', 'Chinamasa', 'Tshuma', 'Khumalo', 'Mlambo', 'Ruzvidzo', 'Chitando', 'Gwenzi', 'Madziva', 'Nhamo',
    ];

    /** Share of listings per type (sums to 100). */
    private const TYPE_MIX = [
        'house' => 30, 'flat' => 18, 'apartment' => 14, 'townhouse' => 12,
        'cottage' => 9, 'room' => 9, 'commercial' => 5, 'land' => 3,
    ];

    private const ADJECTIVES = ['Modern', 'Spacious', 'Elegant', 'Bright', 'Renovated', 'Secure', 'Stylish', 'Charming', 'Quiet', 'Family'];

    private array $locations = [];

    private array $cityCentres = [];

    private string $password;

    public function handle(): int
    {
        mt_srand((int) $this->option('seed'));
        $owners = max(1, (int) $this->option('owners'));
        $tenants = max(1, (int) $this->option('tenants'));
        $properties = max(1, (int) $this->option('properties'));

        if ($this->option('fresh')) {
            $this->removePrevious();
        } elseif (DB::table('auth_users')->where('email', 'like', '%@'.self::EMAIL_DOMAIN)->exists()) {
            $this->error('A load-test data set already exists. Re-run with --fresh to replace it.');

            return self::FAILURE;
        }

        if (! $this->loadLocations()) {
            $this->error('The locations table is empty. Run: php artisan db:seed --class=LocationSeeder');

            return self::FAILURE;
        }

        $this->password = Hash::make('password123');
        $started = microtime(true);

        $ownerIds = $this->createUsers('owner', $owners, 'Owner');
        $tenantIds = $this->createUsers('tenant', $tenants, 'Tenant');
        $this->createTenantProfiles($tenantIds);

        $listingCounts = $this->splitListings($properties, count($ownerIds));
        $listings = $this->createProperties($ownerIds, $listingCounts);
        $this->createSubscriptions($ownerIds, $listings);
        $this->createImages($listings);

        $available = array_values(array_filter($listings, fn (array $p) => $p['status'] === 'available'));
        $this->createFavourites($tenantIds, $listings);
        $this->createViews($tenantIds, $available);
        $this->createEnquiries($tenantIds, $available);
        $this->createInterests($tenantIds, $available);
        $this->createViewings($tenantIds, $available);
        $this->createApplications($tenantIds, $available);
        $this->createSavedSearches($tenantIds);
        $this->createReports($tenantIds, $available);

        $this->newLine();
        $this->info(sprintf('Load-test data ready in %.1fs. Every account uses password "password123" and an @%s email.', microtime(true) - $started, self::EMAIL_DOMAIN));
        $this->table(['Table', 'Rows'], collect([
            'auth_users', 'properties', 'property_images', 'property_favourites', 'property_views', 'enquiries',
            'express_interests', 'viewing_slots', 'viewing_requests', 'rental_applications', 'saved_searches', 'reports',
        ])->map(fn (string $table) => [$table, number_format(DB::table($table)->count())])->all());

        return self::SUCCESS;
    }

    private function removePrevious(): void
    {
        $ids = DB::table('auth_users')->where('email', 'like', '%@'.self::EMAIL_DOMAIN)->pluck('id');
        if ($ids->isEmpty()) {
            return;
        }

        $this->info("Removing previous load-test data ({$ids->count()} accounts)…");
        foreach ($ids->chunk(500) as $chunk) {
            // Reports point at users/properties without a foreign key; clear them first.
            $propertyIds = DB::table('properties')->whereIn('owner_id', $chunk)->pluck('id');
            foreach ($propertyIds->chunk(1000) as $properties) {
                DB::table('reports')->where('subject_type', 'property')->whereIn('subject_id', $properties)->delete();
            }
            DB::table('reports')->whereIn('reporter_id', $chunk)->delete();
            DB::table('auth_users')->whereIn('id', $chunk)->delete();
        }
    }

    private function loadLocations(): bool
    {
        foreach (Location::cities()->get() as $city) {
            $this->cityCentres[$city->name] = [(float) $city->latitude, (float) $city->longitude];
        }

        $weights = ['Harare' => 10, 'Bulawayo' => 5, 'Chitungwiza' => 3, 'Ruwa' => 2, 'Mutare' => 2, 'Gweru' => 2];
        foreach (Location::suburbs()->whereNotIn('density', ['industrial'])->get() as $suburb) {
            $weight = ($weights[$suburb->city] ?? 1) * ($suburb->is_popular ? 3 : 1);
            for ($i = 0; $i < $weight; $i++) {
                $this->locations[] = [
                    'suburb' => $suburb->name,
                    'zone' => $suburb->zone,
                    'city' => $suburb->city,
                    'density' => $suburb->density,
                ];
            }
        }

        return $this->locations !== [];
    }

    /**
     * @return array<int, int> user ids
     */
    private function createUsers(string $kind, int $count, string $roleName): array
    {
        $this->info("Creating {$count} {$kind}s…");
        $now = now();
        $rows = [];
        for ($i = 1; $i <= $count; $i++) {
            $first = self::FIRST_NAMES[mt_rand(0, count(self::FIRST_NAMES) - 1)];
            $last = self::LAST_NAMES[mt_rand(0, count(self::LAST_NAMES) - 1)];
            $isOwner = $kind === 'owner';
            $verified = $isOwner && mt_rand(1, 100) <= 45;
            $rows[] = [
                'name' => "{$first} {$last}",
                'email' => "{$kind}{$i}@".self::EMAIL_DOMAIN,
                'username' => "lt_{$kind}{$i}",
                'password' => $this->password,
                'status' => 'active',
                'password_changed_at' => $now,
                'password_expires_at' => $now->copy()->addMonths(5),
                'verified' => $verified,
                'verified_at' => $verified ? $now : null,
                'badge_tier' => $verified ? ['silver', 'gold', 'gold'][mt_rand(0, 2)] : 'none',
                'rating_avg' => $isOwner ? mt_rand(35, 50) / 10 : 0,
                'ratings_count' => $isOwner ? mt_rand(0, 40) : 0,
                'created_at' => $now->copy()->subDays(mt_rand(10, 400)),
                'updated_at' => $now,
            ];
        }
        $this->insertChunked('auth_users', $rows);

        $ids = DB::table('auth_users')->where('email', 'like', "{$kind}%@".self::EMAIL_DOMAIN)->orderBy('id')->pluck('id')->all();
        $roleId = Role::where('name', $roleName)->value('id');
        $this->insertChunked('auth_user_roles', array_map(fn (int $id) => [
            'user_id' => $id, 'role_id' => $roleId, 'created_at' => $now, 'updated_at' => $now,
        ], $ids));

        return $ids;
    }

    private function createTenantProfiles(array $tenantIds): void
    {
        $now = now();
        $cities = array_keys($this->cityCentres);
        $rows = [];
        foreach ($tenantIds as $id) {
            if (mt_rand(1, 100) > 70) {
                continue;
            }
            $rows[] = [
                'user_id' => $id,
                'phone' => '+2637'.mt_rand(1, 8).mt_rand(1000000, 9999999),
                'city' => mt_rand(1, 100) <= 60 ? 'Harare' : $cities[mt_rand(0, count($cities) - 1)],
                'employment_status' => TenantProfile::EMPLOYMENT_STATUSES[mt_rand(0, count(TenantProfile::EMPLOYMENT_STATUSES) - 1)],
                'salary_band' => TenantProfile::SALARY_BANDS[mt_rand(0, count(TenantProfile::SALARY_BANDS) - 1)],
                'preferred_contact' => TenantProfile::PREFERRED_CONTACTS[mt_rand(0, count(TenantProfile::PREFERRED_CONTACTS) - 1)],
                'about' => 'Reliable tenant looking for a long-term home. References available on request.',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        $this->insertChunked('tenant_profiles', $rows);
    }

    /**
     * A realistic spread: most owners have a handful of listings, a few run portfolios.
     *
     * @return array<int, int>
     */
    private function splitListings(int $total, int $owners): array
    {
        $weights = [];
        for ($i = 0; $i < $owners; $i++) {
            $roll = mt_rand(1, 100);
            $weights[] = $roll <= 55 ? mt_rand(1, 5) : ($roll <= 85 ? mt_rand(6, 20) : mt_rand(21, 60));
        }
        $sum = array_sum($weights);
        $counts = array_map(fn (int $w) => max(1, (int) floor($w / $sum * $total)), $weights);
        $counts[0] += max(0, $total - array_sum($counts));

        return $counts;
    }

    /**
     * @return array<int, array{id: int, owner_id: int, type: string, status: string, price: float, city: string, suburb: string}>
     */
    private function createProperties(array $ownerIds, array $counts): array
    {
        $total = array_sum($counts);
        $this->info("Creating {$total} listings…");
        $now = now();
        $types = [];
        foreach (self::TYPE_MIX as $type => $share) {
            array_push($types, ...array_fill(0, $share, $type));
        }
        $amenityPool = ['borehole', 'solar', 'backup_power', 'water_tank', 'internet', 'garden', 'pool', 'security', 'gated_community', 'carport', 'secure_parking', 'double_garage', 'automated_gate', 'air_conditioning', 'built_in_cupboards', 'ensuite', 'balcony', 'pet_friendly', 'prepaid_utilities', 'servants_quarters'];

        $rows = [];
        foreach ($ownerIds as $index => $ownerId) {
            for ($n = 0; $n < $counts[$index]; $n++) {
                $type = $types[mt_rand(0, count($types) - 1)];
                $place = $this->locations[mt_rand(0, count($this->locations) - 1)];
                [$bedrooms, $bathrooms] = $this->rooms($type);
                $monthly = $this->monthlyRent($type, $bedrooms, $place);
                $terms = $type === 'land' || $type === 'commercial'
                    ? ['monthly', 'quarterly', 'yearly'][mt_rand(0, 2)]
                    : (mt_rand(1, 100) <= 90 ? 'monthly' : 'quarterly');
                $price = match ($terms) {
                    'quarterly' => $monthly * 3,
                    'yearly' => $monthly * 11,
                    default => $monthly,
                };
                $status = mt_rand(1, 100) <= 92 ? 'available' : 'unavailable';
                $created = $now->copy()->subDays(mt_rand(0, 120))->subMinutes(mt_rand(0, 1440));
                [$lat, $lng] = $this->coordinates($place);
                shuffle($amenityPool);
                $size = $type === 'land' ? null : (int) (35 + $bedrooms * mt_rand(28, 55) + ($type === 'commercial' ? mt_rand(40, 400) : 0));

                $rows[] = [
                    'owner_id' => $ownerId,
                    'title' => $this->title($type, $bedrooms, $place['suburb']),
                    'description' => $this->description($type, $bedrooms, $place),
                    'property_type' => $type,
                    'bedrooms' => $bedrooms,
                    'bathrooms' => $bathrooms,
                    'building_size' => $size,
                    'land_size' => in_array($type, ['house', 'land', 'cottage'], true) ? mt_rand(300, 4000) : null,
                    'price' => $price,
                    'deposit' => in_array($type, ['room', 'land'], true) ? $monthly : $monthly * mt_rand(1, 2),
                    'furnished' => $type === 'room' ? mt_rand(0, 100) <= 70 : mt_rand(0, 100) <= 25,
                    'status' => $status,
                    'suburb' => $place['suburb'],
                    'zone' => $place['zone'],
                    'city' => $place['city'],
                    'address' => mt_rand(1, 250).' '.['Enterprise', 'Churchill', 'Borrowdale', 'Samora Machel', 'Josiah Tongogara', 'Herbert Chitepo', 'Lomagundi', 'Harare Drive', 'Jason Moyo', 'Leopold Takawira'][mt_rand(0, 9)].' '.['Road', 'Avenue', 'Drive', 'Street'][mt_rand(0, 3)],
                    'amenities' => json_encode(array_slice($amenityPool, 0, $type === 'land' ? 1 : mt_rand(2, 7))),
                    'featured' => $status === 'available' && mt_rand(1, 100) <= 4,
                    'verified' => mt_rand(1, 100) <= 35,
                    'available_from' => mt_rand(1, 100) <= 80 ? null : $now->copy()->addDays(mt_rand(3, 45))->toDateString(),
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'expires_at' => $status === 'available' ? $now->copy()->addDays(mt_rand(5, 60)) : $now->copy()->subDays(mt_rand(1, 20)),
                    'currency' => mt_rand(1, 100) <= 96 ? 'USD' : 'ZWL',
                    'payment_terms' => $terms,
                    'water_cost' => in_array($type, ['land', 'commercial'], true) ? null : mt_rand(0, 3) * 10,
                    'electricity_cost' => in_array($type, ['land'], true) ? null : mt_rand(0, 6) * 10,
                    'trash_cost' => mt_rand(0, 1) * 5,
                    'negotiable' => mt_rand(1, 100) <= 30,
                    'year_built' => $type === 'land' ? null : mt_rand(1965, 2024),
                    'entrance_type' => in_array($type, ['room', 'cottage'], true) ? ['own', 'shared'][mt_rand(0, 1)] : 'own',
                    'bathroom_type' => $type === 'room' ? ['own', 'shared'][mt_rand(0, 1)] : 'own',
                    'parking_type' => ['none', 'street', 'secure', 'secure'][mt_rand(0, 3)],
                    'security_type' => ['gated', 'fenced', 'none'][mt_rand(0, 2)],
                    'distance_to_cbd' => mt_rand(5, 250) / 10,
                    'children_allowed' => mt_rand(1, 100) <= 80,
                    'pets_allowed' => mt_rand(1, 100) <= 35,
                    'smoking_allowed' => mt_rand(1, 100) <= 10,
                    'parties_allowed' => mt_rand(1, 100) <= 5,
                    'minimum_stay' => [1, 3, 6, 12, 12, 12][mt_rand(0, 5)],
                    'preferred_tenant' => ['any', 'any', 'family', 'single', 'professionals', 'students'][mt_rand(0, 5)],
                    'landlord_type' => 'direct',
                    'contact_preference' => ['platform', 'whatsapp', 'call', 'email'][mt_rand(0, 3)],
                    'show_phone' => mt_rand(1, 100) <= 40,
                    'created_at' => $created,
                    'updated_at' => $created,
                ];
            }
        }
        $this->insertChunked('properties', $rows);

        return DB::table('properties')
            ->whereIn('owner_id', $ownerIds)
            ->orderBy('id')
            ->get(['id', 'owner_id', 'property_type', 'status', 'price', 'city', 'suburb', 'bedrooms', 'created_at'])
            ->map(fn ($p) => [
                'id' => (int) $p->id,
                'owner_id' => (int) $p->owner_id,
                'type' => $p->property_type,
                'status' => $p->status,
                'price' => (float) $p->price,
                'city' => $p->city,
                'suburb' => $p->suburb,
                'bedrooms' => (int) $p->bedrooms,
                'created_at' => Carbon::parse($p->created_at),
            ])
            ->all();
    }

    /** Plan per owner sized so their available listings stay within its limit. */
    private function createSubscriptions(array $ownerIds, array $listings): void
    {
        $this->info('Assigning subscription plans…');
        $plans = SubscriptionPlan::where('status', 'active')->get()->keyBy('name');
        $available = [];
        foreach ($listings as $listing) {
            if ($listing['status'] === 'available') {
                $available[$listing['owner_id']] = ($available[$listing['owner_id']] ?? 0) + 1;
            }
        }

        $now = now();
        $rows = [];
        foreach ($ownerIds as $ownerId) {
            $count = $available[$ownerId] ?? 0;
            $plan = $plans->filter(fn ($p) => $p->listing_limit === null || $p->listing_limit >= $count)
                ->sortBy(fn ($p) => $p->listing_limit ?? PHP_INT_MAX)
                ->first();
            $starts = $now->copy()->subDays(mt_rand(1, 25));
            $rows[] = [
                'owner_id' => $ownerId,
                'plan_id' => $plan->id,
                'status' => 'active',
                'starts_at' => $starts,
                'ends_at' => $starts->copy()->addDays(30),
                'cycle' => 'monthly',
                'created_at' => $starts,
                'updated_at' => $starts,
            ];
        }
        $this->insertChunked('subscriptions', $rows);
    }

    private function createImages(array $listings): void
    {
        $this->info('Attaching photos…');
        $now = now();
        $rows = [];
        $covers = [];
        foreach ($listings as $listing) {
            foreach (DemoPhotos::forListing($listing['type'], $listing['id']) as $sort => $url) {
                $rows[] = ['property_id' => $listing['id'], 'path' => $url, 'caption' => null, 'sort_order' => $sort, 'created_at' => $now, 'updated_at' => $now];
                if ($sort === 0) {
                    $covers[$listing['id']] = $url;
                }
            }
        }
        $this->insertChunked('property_images', $rows);

        foreach (array_chunk($covers, self::CHUNK, true) as $chunk) {
            $cases = collect($chunk)->map(fn ($url, $id) => 'WHEN '.(int) $id.' THEN '.DB::getPdo()->quote($url))->implode(' ');
            DB::update('UPDATE properties SET cover_image = CASE id '.$cases.' END WHERE id IN ('.implode(',', array_keys($chunk)).')');
        }
    }

    private function createFavourites(array $tenantIds, array $listings): void
    {
        $this->info('Saving favourites…');
        $now = now();
        $rows = [];
        foreach ($tenantIds as $tenantId) {
            foreach ($this->pickDistinct($listings, mt_rand(0, 14)) as $listing) {
                $rows[] = ['user_id' => $tenantId, 'property_id' => $listing['id'], 'created_at' => $now, 'updated_at' => $now];
            }
        }
        $this->insertChunked('property_favourites', $rows);
    }

    private function createViews(array $tenantIds, array $available): void
    {
        $this->info('Recording page views (last 30 days)…');
        $now = now();
        $rows = [];
        foreach ($available as $listing) {
            $views = mt_rand(0, 100) <= 10 ? mt_rand(60, 140) : mt_rand(3, 35);
            for ($v = 0; $v < $views; $v++) {
                $at = $now->copy()->subMinutes(mt_rand(0, 30 * 24 * 60));
                $rows[] = [
                    'property_id' => $listing['id'],
                    'user_id' => mt_rand(1, 100) <= 45 ? $tenantIds[mt_rand(0, count($tenantIds) - 1)] : null,
                    'ip' => '10.'.mt_rand(0, 255).'.'.mt_rand(0, 255).'.'.mt_rand(1, 254),
                    'viewed_at' => $at,
                    'created_at' => $at,
                    'updated_at' => $at,
                ];
            }
            if (count($rows) >= 20000) {
                $this->insertChunked('property_views', $rows);
                $rows = [];
            }
        }
        $this->insertChunked('property_views', $rows);
    }

    private function createEnquiries(array $tenantIds, array $available): void
    {
        $this->info('Creating enquiries…');
        $questions = [
            'Is this property still available? I would like to arrange a viewing this week.',
            'Hi, is the rent negotiable for a 12 month lease?',
            'Does the property have a borehole or reliable water supply?',
            'Is there backup power (solar or generator)?',
            'Are pets allowed? I have one small dog.',
            'When is the earliest move-in date?',
            'Is the deposit refundable and how many months are required?',
        ];
        $replies = [
            'Yes, it is still available. You are welcome to book one of the viewing times on the listing.',
            'Thanks for your interest. The rent is fixed but I can be flexible on the move-in date.',
            'Yes, there is a borehole and a 5,000 litre tank.',
            'There is a solar backup system for lights and plugs.',
        ];
        $rows = [];
        foreach ($this->uniquePairs($tenantIds, $available, (int) (count($tenantIds) * 3)) as [$tenantId, $listing]) {
            $created = $this->after($listing['created_at']);
            $status = $this->weighted(['new' => 30, 'read' => 20, 'replied' => 35, 'closed' => 15]);
            $replied = in_array($status, ['replied', 'closed'], true);
            $rows[] = [
                'property_id' => $listing['id'],
                'tenant_id' => $tenantId,
                'message' => $questions[mt_rand(0, count($questions) - 1)],
                'phone' => mt_rand(0, 1) ? '+2637'.mt_rand(1, 8).mt_rand(1000000, 9999999) : null,
                'reply' => $replied ? $replies[mt_rand(0, count($replies) - 1)] : null,
                'replied_at' => $replied ? $created->copy()->addHours(mt_rand(1, 48)) : null,
                'status' => $status,
                'read_at' => $status === 'new' ? null : $created->copy()->addMinutes(mt_rand(5, 600)),
                'created_at' => $created,
                'updated_at' => $created,
            ];
        }
        $this->insertChunked('enquiries', $rows);

        // Owner replies also live in the append-only thread (enquiry_messages).
        DB::table('enquiries')
            ->join('properties', 'properties.id', '=', 'enquiries.property_id')
            ->whereIn('enquiries.tenant_id', $tenantIds)
            ->whereNotNull('enquiries.reply')
            ->select('enquiries.id', 'enquiries.reply', 'enquiries.replied_at', 'properties.owner_id')
            ->orderBy('enquiries.id')
            ->chunk(self::CHUNK, function ($replied) {
                DB::table('enquiry_messages')->insert($replied->map(fn ($row) => [
                    'enquiry_id' => $row->id,
                    'sender_id' => $row->owner_id,
                    'sender_role' => 'owner',
                    'body' => $row->reply,
                    'created_at' => $row->replied_at,
                    'updated_at' => $row->replied_at,
                ])->all());
            });
    }

    private function createInterests(array $tenantIds, array $available): void
    {
        $this->info('Creating expressed interests…');
        $rows = [];
        foreach ($this->uniquePairs($tenantIds, $available, (int) (count($tenantIds) * 2)) as [$tenantId, $listing]) {
            $created = $this->after($listing['created_at']);
            $rows[] = [
                'property_id' => $listing['id'],
                'tenant_id' => $tenantId,
                'note' => mt_rand(0, 1) ? 'Very interested — please contact me.' : null,
                'status' => $this->weighted(['interested' => 60, 'contacted' => 25, 'archived' => 15]),
                'created_at' => $created,
                'updated_at' => $created,
            ];
        }
        $this->insertChunked('express_interests', $rows);
    }

    private function createViewings(array $tenantIds, array $available): void
    {
        $this->info('Creating viewing slots and requests…');
        $now = now()->startOfHour();
        $slotRows = [];
        foreach ($available as $listing) {
            if (mt_rand(1, 100) > 40) {
                continue;
            }
            for ($s = 0; $s < 3; $s++) {
                $start = $now->copy()->addDays(mt_rand(1, 14))->setTime(mt_rand(8, 16), [0, 30][mt_rand(0, 1)]);
                $slotRows[] = [
                    'property_id' => $listing['id'],
                    'owner_id' => $listing['owner_id'],
                    'starts_at' => $start,
                    'ends_at' => $start->copy()->addMinutes(45),
                    'status' => 'available',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        $this->insertChunked('viewing_slots', $slotRows);

        $slots = DB::table('viewing_slots')
            ->whereIn('property_id', array_column($available, 'id'))
            ->where('status', 'available')
            ->get(['id', 'property_id'])
            ->all();

        $requestRows = [];
        $takenSlots = [];
        $seen = [];
        foreach ($slots as $slot) {
            if (mt_rand(1, 100) > 45) {
                continue;
            }
            $accepted = mt_rand(1, 100) <= 40;
            $requesters = $accepted ? 1 : mt_rand(1, 3);
            for ($r = 0; $r < $requesters; $r++) {
                $tenantId = $tenantIds[mt_rand(0, count($tenantIds) - 1)];
                $key = $tenantId.':'.$slot->id;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $requestRows[] = [
                    'property_id' => $slot->property_id,
                    'tenant_id' => $tenantId,
                    'slot_id' => $slot->id,
                    'request_message' => mt_rand(0, 1) ? 'I would like to view the property. Thank you.' : null,
                    'status' => $accepted ? 'accepted' : 'requested',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            if ($accepted) {
                $takenSlots[] = $slot->id;
            }
        }
        $this->insertChunked('viewing_requests', $requestRows);
        foreach (array_chunk($takenSlots, self::CHUNK) as $chunk) {
            DB::table('viewing_slots')->whereIn('id', $chunk)->update(['status' => 'taken']);
        }
    }

    private function createApplications(array $tenantIds, array $available): void
    {
        $this->info('Creating rental applications…');
        $approvedProperty = [];
        $rows = [];
        foreach ($this->uniquePairs($tenantIds, $available, (int) (count($tenantIds) * 1.75)) as [$tenantId, $listing]) {
            $status = $this->weighted(['pending' => 50, 'shortlisted' => 20, 'rejected' => 25, 'approved' => 5]);
            if ($status === 'approved' && isset($approvedProperty[$listing['id']])) {
                $status = 'shortlisted';
            }
            if ($status === 'approved') {
                $approvedProperty[$listing['id']] = true;
            }
            $created = $this->after($listing['created_at']);
            $rows[] = [
                'property_id' => $listing['id'],
                'applicant_id' => $tenantId,
                'message' => 'I am a working professional with references and can move in at the start of next month.',
                'reject_reason' => $status === 'rejected' ? 'Another applicant better matched the household size.' : null,
                'status' => $status,
                'reviewed_by' => in_array($status, ['approved', 'rejected'], true) ? $listing['owner_id'] : null,
                'created_at' => $created,
                'updated_at' => $created,
            ];
        }
        $this->insertChunked('rental_applications', $rows);
    }

    private function createSavedSearches(array $tenantIds): void
    {
        $this->info('Creating saved searches…');
        $now = now();
        $rows = [];
        foreach ($tenantIds as $tenantId) {
            if (mt_rand(1, 100) > 30) {
                continue;
            }
            for ($i = 0, $n = mt_rand(1, 3); $i < $n; $i++) {
                $place = $this->locations[mt_rand(0, count($this->locations) - 1)];
                $bedrooms = mt_rand(1, 4);
                $max = [300, 500, 800, 1200, 2000][mt_rand(0, 4)];
                $rows[] = [
                    'user_id' => $tenantId,
                    'name' => "{$bedrooms}+ bed in {$place['suburb']} under \${$max}",
                    'criteria' => json_encode(['city' => $place['city'], 'suburb' => $place['suburb'], 'bedrooms' => $bedrooms, 'max_price' => $max]),
                    'notify' => mt_rand(1, 100) <= 75,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        $this->insertChunked('saved_searches', $rows);
    }

    private function createReports(array $tenantIds, array $available): void
    {
        $this->info('Creating listing reports…');
        $categories = ['suspicious_listing', 'incorrect_information', 'duplicate', 'wrong_price', 'already_rented'];
        $now = now();
        $rows = [];
        foreach ($this->pickDistinct($available, min(250, count($available))) as $listing) {
            $rows[] = [
                'reporter_id' => mt_rand(1, 100) <= 70 ? $tenantIds[mt_rand(0, count($tenantIds) - 1)] : null,
                'subject_type' => 'property',
                'subject_id' => $listing['id'],
                'category' => $categories[mt_rand(0, count($categories) - 1)],
                'description' => 'The details on this listing do not match what I saw. Please check.',
                'status' => $this->weighted(['open' => 70, 'under_review' => 20, 'escalated' => 10]),
                'priority' => ['low', 'medium', 'medium', 'high'][mt_rand(0, 3)],
                'created_at' => $now->copy()->subDays(mt_rand(0, 20)),
                'updated_at' => $now,
            ];
        }
        $this->insertChunked('reports', $rows);
    }

    private function rooms(string $type): array
    {
        $bedrooms = match ($type) {
            'house' => mt_rand(2, 5),
            'townhouse' => mt_rand(2, 4),
            'flat', 'apartment' => mt_rand(1, 3),
            'cottage' => mt_rand(1, 2),
            'room' => 1,
            default => 0,
        };
        $bathrooms = match ($type) {
            'land' => 0,
            'commercial' => mt_rand(1, 3),
            'room' => 1,
            default => max(1, $bedrooms - mt_rand(0, 1)),
        };

        return [$bedrooms, $bathrooms];
    }

    private function monthlyRent(string $type, int $bedrooms, array $place): float
    {
        $base = match ($type) {
            'house' => 450 + 230 * $bedrooms,
            'townhouse' => 400 + 200 * $bedrooms,
            'flat', 'apartment' => 250 + 170 * $bedrooms,
            'cottage' => 220 + 120 * $bedrooms,
            'room' => 110,
            'commercial' => 900,
            default => 250,
        };
        $density = ['low' => 1.0, 'medium' => 0.55, 'high' => 0.28, 'commercial' => 1.1][$place['density']] ?? 0.6;
        $city = ['Harare' => 1.0, 'Bulawayo' => 0.75, 'Victoria Falls' => 0.9, 'Chitungwiza' => 0.55, 'Epworth' => 0.45, 'Ruwa' => 0.7, 'Norton' => 0.6][$place['city']] ?? 0.55;

        return max(50, round($base * $density * $city * mt_rand(85, 120) / 100 / 10) * 10);
    }

    private function coordinates(array $place): array
    {
        [$lat, $lng] = $this->cityCentres[$place['city']] ?? [-17.8292, 31.0522];
        $shift = match (true) {
            str_contains($place['zone'] ?? '', 'North') => [0.045, 0.0],
            str_contains($place['zone'] ?? '', 'South') => [-0.045, 0.0],
            str_contains($place['zone'] ?? '', 'East') => [0.0, 0.055],
            str_contains($place['zone'] ?? '', 'West') => [0.0, -0.055],
            default => [0.0, 0.0],
        };
        // Stable per-suburb offset so homes in one suburb cluster together.
        $hash = crc32($place['city'].$place['suburb']);
        $suburbLat = (($hash % 1000) / 1000 - 0.5) * 0.05;
        $suburbLng = ((intdiv($hash, 1000) % 1000) / 1000 - 0.5) * 0.05;

        return [
            round($lat + $shift[0] + $suburbLat + mt_rand(-60, 60) / 10000, 7),
            round($lng + $shift[1] + $suburbLng + mt_rand(-60, 60) / 10000, 7),
        ];
    }

    private function title(string $type, int $bedrooms, string $suburb): string
    {
        $adjective = self::ADJECTIVES[mt_rand(0, count(self::ADJECTIVES) - 1)];

        return match ($type) {
            'house' => "{$adjective} {$bedrooms}-Bedroom House in {$suburb}",
            'townhouse' => "{$adjective} {$bedrooms}-Bedroom Townhouse in {$suburb}",
            'flat' => "{$bedrooms}-Bedroom {$adjective} Flat in {$suburb}",
            'apartment' => "{$adjective} {$bedrooms}-Bed Apartment in {$suburb}",
            'cottage' => "{$adjective} Garden Cottage in {$suburb}",
            'room' => ['Furnished Room', 'Room to Rent', 'Student Room', 'Self-contained Room'][mt_rand(0, 3)]." in {$suburb}",
            'commercial' => ['Office Space', 'Retail Shop', 'Commercial Unit', 'Warehouse Space'][mt_rand(0, 3)]." in {$suburb}",
            default => mt_rand(4, 40) * 100 ." m² Stand for Lease in {$suburb}",
        };
    }

    private function description(string $type, int $bedrooms, array $place): string
    {
        $intro = match ($type) {
            'land' => "A level, serviced stand in {$place['suburb']}, {$place['city']}, available on a long-term lease.",
            'commercial' => "Well-positioned commercial space in {$place['suburb']} with good access and parking.",
            'room' => "A clean, secure room in {$place['suburb']}, ideal for a working professional or student.",
            default => "A well-kept {$bedrooms}-bedroom home in the {$place['suburb']} area of {$place['city']}.",
        };
        $extras = [
            'Close to shops, schools and public transport.',
            'Reliable water supply with a borehole and storage tank.',
            'Solar backup keeps the lights on during load-shedding.',
            'Secure property with a durawall and electric gate.',
            'Quiet, leafy neighbourhood with friendly neighbours.',
            'Recently repainted with modern fittings throughout.',
        ];
        shuffle($extras);

        return $intro.' '.implode(' ', array_slice($extras, 0, 3)).' Rented directly by the owner — no agent commission.';
    }

    /**
     * Distinct tenant×listing pairs (the app allows one open enquiry, interest
     * or active application per pair).
     *
     * @return array<int, array{0: int, 1: array}>
     */
    private function uniquePairs(array $tenantIds, array $listings, int $count): array
    {
        $pairs = [];
        $seen = [];
        $attempts = 0;
        while (count($pairs) < $count && $attempts < $count * 3) {
            $attempts++;
            $tenantId = $tenantIds[mt_rand(0, count($tenantIds) - 1)];
            $listing = $listings[mt_rand(0, count($listings) - 1)];
            $key = $tenantId.':'.$listing['id'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $pairs[] = [$tenantId, $listing];
        }

        return $pairs;
    }

    private function pickDistinct(array $items, int $count): array
    {
        if ($count <= 0 || $items === []) {
            return [];
        }
        $keys = (array) array_rand($items, min($count, count($items)));

        return array_map(fn ($key) => $items[$key], $keys);
    }

    private function weighted(array $weights): string
    {
        $roll = mt_rand(1, array_sum($weights));
        foreach ($weights as $value => $weight) {
            $roll -= $weight;
            if ($roll <= 0) {
                return $value;
            }
        }

        return array_key_first($weights);
    }

    private function after(Carbon $moment): Carbon
    {
        $minutes = max(1, (int) $moment->diffInMinutes(now(), true));

        return $moment->copy()->addMinutes(mt_rand(1, $minutes));
    }

    private function insertChunked(string $table, array $rows): void
    {
        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }
}
