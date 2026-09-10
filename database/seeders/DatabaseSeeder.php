<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AuthSeeder::class,
            SubscriptionPlanSeeder::class,
            PropertySeeder::class,
            AdvertisingSeeder::class,
            ConfigSeeder::class,
        ]);
    }
}