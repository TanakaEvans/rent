<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preservation-Release 2.0 (S1): the Zimbabwe listing taxonomy and
 * structured, option-driven listing model.
 *
 *  - `property_type` drops the hard enum for a string so the catalogue can
 *    grow (`apartment` joins today; more can arrive without migrations).
 *  - Currency-aware pricing (USD / ZWL) with structured extra costs and
 *    payment terms — money stays DECIMAL(12,2), never float.
 *  - Room-type structured options (own/shared entrance + bathroom, parking,
 *    families allowed, distance to CBD, gated/fenced security).
 *  - House rules, minimum stay, preferred tenant, landlord type and contact
 *    preference — every answer is an option, not free text.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            // Typed entry becomes a string so the catalogue grows without
            // future enum alterations (`apartment` is the first addition).
            $table->string('property_type', 50)->default('house')->change();

            // Currency-aware pricing (FR-06 / PR-2.0 S1).
            $table->enum('currency', ['USD', 'ZWL'])->default('USD');
            $table->enum('payment_terms', ['monthly', 'quarterly', 'yearly'])->default('monthly');
            $table->decimal('water_cost', 12, 2)->nullable();
            $table->decimal('electricity_cost', 12, 2)->nullable();
            $table->decimal('trash_cost', 12, 2)->nullable();
            $table->boolean('negotiable')->default(false);

            // Building facts.
            $table->decimal('floor_area', 10, 2)->nullable();
            $table->unsignedSmallInteger('year_built')->nullable();

            // Room-type structured options (Rooms category).
            $table->enum('entrance_type', ['own', 'shared'])->nullable();
            $table->enum('bathroom_type', ['own', 'shared'])->nullable();
            $table->enum('parking_type', ['none', 'street', 'secure'])->default('none');
            $table->boolean('families_allowed')->nullable();
            $table->decimal('distance_to_cbd', 8, 2)->nullable();
            $table->enum('security_type', ['gated', 'fenced', 'none'])->default('none');

            // House rules + target tenant (options, not prose).
            $table->boolean('children_allowed')->default(true);
            $table->boolean('pets_allowed')->default(false);
            $table->boolean('smoking_allowed')->default(false);
            $table->boolean('parties_allowed')->default(false);
            $table->unsignedSmallInteger('minimum_stay')->default(12);
            $table->enum('preferred_tenant', ['any', 'family', 'single', 'professionals', 'students'])->default('any');

            // Listing/contact details.
            $table->enum('landlord_type', ['direct', 'agent', 'corporate'])->default('direct');
            $table->enum('contact_preference', ['platform', 'whatsapp', 'call', 'email'])->default('platform');
            $table->boolean('show_phone')->default(false);
            $table->string('landmark', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn([
                'currency', 'payment_terms', 'water_cost', 'electricity_cost', 'trash_cost', 'negotiable',
                'floor_area', 'year_built', 'entrance_type', 'bathroom_type', 'parking_type',
                'families_allowed', 'distance_to_cbd', 'security_type',
                'children_allowed', 'pets_allowed', 'smoking_allowed', 'parties_allowed',
                'minimum_stay', 'preferred_tenant', 'landlord_type', 'contact_preference',
                'show_phone', 'landmark',
            ]);

            $table->enum('property_type', ['house', 'flat', 'townhouse', 'cottage', 'room', 'commercial', 'land'])
                ->default('house')->change();
        });
    }
};