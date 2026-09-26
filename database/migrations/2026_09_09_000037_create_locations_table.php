<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Zimbabwe location catalogue: every province's cities and towns, the
     * suburbs/neighbourhoods inside them (grouped into zones with a density
     * band), and well-known landmarks tenants search by (malls, campuses,
     * hospitals, airports). One row per (type, city, name).
     */
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->string('name', 120);
            $table->string('province', 60);
            $table->string('city', 100);
            $table->string('zone', 100)->nullable();
            $table->string('suburb', 100)->nullable();
            $table->string('density', 20)->nullable();
            $table->string('category', 30)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_popular')->default(false);
            $table->timestamps();

            $table->unique(['type', 'city', 'name']);
            $table->index(['type', 'province']);
            $table->index(['city', 'zone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
