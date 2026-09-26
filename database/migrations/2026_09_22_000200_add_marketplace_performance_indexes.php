<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the queries that dominate marketplace load at scale (measured
 * with `php artisan zimrent:benchmark` on 6,000 listings / 150,000 views):
 * the featured-first listing order, the area/zone facets and "explore" rail,
 * type filters, and the 30-day view windows used by analytics.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->index(['status', 'featured', 'created_at'], 'properties_status_featured_created_index');
            $table->index(['status', 'created_at'], 'properties_status_created_index');
            $table->index(['status', 'suburb', 'city'], 'properties_status_suburb_city_index');
            $table->index(['status', 'zone'], 'properties_status_zone_index');
            $table->index(['status', 'property_type'], 'properties_status_type_index');
        });

        Schema::table('property_views', function (Blueprint $table) {
            $table->index(['viewed_at', 'property_id'], 'property_views_viewed_at_property_index');
        });
    }

    public function down(): void
    {
        Schema::table('property_views', function (Blueprint $table) {
            $table->dropIndex('property_views_viewed_at_property_index');
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex('properties_status_featured_created_index');
            $table->dropIndex('properties_status_created_index');
            $table->dropIndex('properties_status_suburb_city_index');
            $table->dropIndex('properties_status_zone_index');
            $table->dropIndex('properties_status_type_index');
        });
    }
};
