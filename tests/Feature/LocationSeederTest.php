<?php

namespace Tests\Feature;

use App\Models\Location;
use Database\Seeders\LocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LocationSeeder::class);
    }

    public function test_every_province_has_at_least_one_city_or_town(): void
    {
        foreach (Location::PROVINCES as $province) {
            $this->assertTrue(
                Location::cities()->where('province', $province)->exists(),
                "No city or town seeded for {$province}."
            );
        }
    }

    public function test_cities_carry_coordinates_and_suburbs_inherit_their_province(): void
    {
        $this->assertSame(0, Location::cities()->whereNull('latitude')->count());

        $borrowdale = Location::suburbs()->inCity('Harare')->where('name', 'Borrowdale')->firstOrFail();
        $this->assertSame('Harare', $borrowdale->province);
        $this->assertSame('Harare North', $borrowdale->zone);
        $this->assertSame('low', $borrowdale->density);
        $this->assertTrue($borrowdale->is_popular);

        $ruwa = Location::cities()->where('name', 'Ruwa')->firstOrFail();
        $this->assertSame('Mashonaland East', $ruwa->province);
    }

    public function test_major_cities_have_their_neighbourhoods(): void
    {
        $this->assertGreaterThanOrEqual(100, Location::suburbs()->inCity('Harare')->count());
        $this->assertGreaterThanOrEqual(60, Location::suburbs()->inCity('Bulawayo')->count());

        foreach (['Chitungwiza', 'Mutare', 'Gweru', 'Kwekwe', 'Masvingo', 'Kadoma', 'Chinhoyi', 'Marondera', 'Victoria Falls'] as $city) {
            $this->assertTrue(Location::suburbs()->inCity($city)->exists(), "No suburbs seeded for {$city}.");
        }

        $this->assertSame(0, Location::suburbs()->whereNotIn('density', Location::DENSITIES)->count());
    }

    public function test_every_seeded_city_or_town_has_at_least_one_area(): void
    {
        $citiesWithoutAreas = Location::cities()
            ->get()
            ->filter(fn (Location $city) => ! Location::suburbs()->inCity($city->name)->exists())
            ->pluck('name')
            ->all();

        $this->assertSame([], $citiesWithoutAreas, 'These towns have no areas seeded: '.implode(', ', $citiesWithoutAreas));
    }

    public function test_landmarks_are_linked_to_their_suburb_and_zone(): void
    {
        $samLevy = Location::landmarks()->where('name', "Sam Levy's Village")->firstOrFail();
        $this->assertSame('Borrowdale', $samLevy->suburb);
        $this->assertSame('Harare North', $samLevy->zone);
        $this->assertSame('shopping', $samLevy->category);

        $this->assertTrue(Location::landmarks()->where('category', 'education')->inCity('Bulawayo')->exists());
    }

    public function test_seeding_twice_does_not_duplicate_rows(): void
    {
        $count = Location::count();

        $this->seed(LocationSeeder::class);

        $this->assertSame($count, Location::count());
    }
}
