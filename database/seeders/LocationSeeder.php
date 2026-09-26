<?php

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Seeds the Zimbabwe location catalogue (cities and towns, suburbs grouped by
 * zone and density, and common landmarks) from database/seeders/data.
 *
 * Idempotent: rows are upserted on (type, city, name), so it can be re-run
 * after the dataset grows without duplicating anything.
 */
class LocationSeeder extends Seeder
{
    public function run(): void
    {
        $data = require __DIR__.'/data/zimbabwe_locations.php';
        $now = now();

        $cities = [];
        foreach ($data['cities'] as [$name, $province, $lat, $lng]) {
            if (! in_array($province, Location::PROVINCES, true)) {
                throw new RuntimeException("Unknown province [{$province}] for city [{$name}].");
            }
            $cities[$name] = compact('province', 'lat', 'lng');
        }

        $popular = $data['popular'];
        $suburbs = [];
        foreach ($data['suburbs'] as $city => $zones) {
            $this->assertCity($cities, $city);
            foreach ($zones as $zone => $densities) {
                foreach ($densities as $density => $names) {
                    foreach ($names as $name) {
                        if (isset($suburbs[$city][$name])) {
                            throw new RuntimeException("Duplicate suburb [{$name}] in [{$city}].");
                        }
                        $suburbs[$city][$name] = compact('zone', 'density');
                    }
                }
            }
        }

        foreach ($popular as $city => $names) {
            foreach ($names as $name) {
                if (! isset($suburbs[$city][$name])) {
                    throw new RuntimeException("Popular suburb [{$name}] is not a seeded suburb of [{$city}].");
                }
            }
        }

        $rows = [];

        foreach ($cities as $name => $city) {
            $rows[] = $this->row('city', $name, $city['province'], $name, [
                'latitude' => $city['lat'],
                'longitude' => $city['lng'],
                'is_popular' => isset($popular[$name]),
            ]);
        }

        foreach ($suburbs as $city => $names) {
            foreach ($names as $name => $meta) {
                $rows[] = $this->row('suburb', $name, $cities[$city]['province'], $city, [
                    'zone' => $meta['zone'],
                    'density' => $meta['density'],
                    'is_popular' => in_array($name, $popular[$city] ?? [], true),
                ]);
            }
        }

        foreach ($data['landmarks'] as [$name, $city, $suburb, $category]) {
            $this->assertCity($cities, $city);
            if ($suburb !== null && ! isset($suburbs[$city][$suburb])) {
                throw new RuntimeException("Landmark [{$name}] references unknown suburb [{$suburb}] in [{$city}].");
            }
            if (! in_array($category, Location::CATEGORIES, true)) {
                throw new RuntimeException("Landmark [{$name}] has unknown category [{$category}].");
            }
            $rows[] = $this->row('landmark', $name, $cities[$city]['province'], $city, [
                'zone' => $suburb !== null ? $suburbs[$city][$suburb]['zone'] : null,
                'suburb' => $suburb,
                'category' => $category,
            ]);
        }

        $rows = array_map(fn (array $row) => $row + ['created_at' => $now, 'updated_at' => $now], $rows);

        foreach (array_chunk($rows, 200) as $chunk) {
            Location::upsert(
                $chunk,
                ['type', 'city', 'name'],
                ['province', 'zone', 'suburb', 'density', 'category', 'latitude', 'longitude', 'is_popular', 'updated_at'],
            );
        }
    }

    private function row(string $type, string $name, string $province, string $city, array $extra = []): array
    {
        return array_merge([
            'type' => $type,
            'name' => $name,
            'province' => $province,
            'city' => $city,
            'zone' => null,
            'suburb' => null,
            'density' => null,
            'category' => null,
            'latitude' => null,
            'longitude' => null,
            'is_popular' => false,
        ], $extra);
    }

    private function assertCity(array $cities, string $city): void
    {
        if (! isset($cities[$city])) {
            throw new RuntimeException("[{$city}] is not a seeded city or town.");
        }
    }
}
