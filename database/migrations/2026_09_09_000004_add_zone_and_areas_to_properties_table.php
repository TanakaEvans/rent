<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->string('zone', 100)->nullable()->after('suburb');
            $table->decimal('building_size', 9, 2)->nullable()->after('bathrooms');
            $table->decimal('land_size', 12, 2)->nullable()->after('building_size');

            $table->index(['status', 'price'], 'properties_status_price_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex('properties_status_price_index');
            $table->dropColumn(['land_size', 'building_size', 'zone']);
        });
    }
};