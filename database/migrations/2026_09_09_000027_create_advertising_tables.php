<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Featured & advertising management (Module 13, Wave 4 slice 6).
     *
     * ad_packages is the priced catalogue (placement_type, price, duration);
     * ad_placements records each purchase with a price snapshot, an explicit
     * state machine (reserved -> active -> expired/cancelled, paused freezes
     * the window) and a prorated credit on cancellation;
     * ad_events tracks impressions/clicks plus resulting enquiries and
     * applications per placement (no personal data beyond the placement id).
     */
    public function up(): void
    {
        Schema::create('ad_packages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 100);
            $table->enum('placement_type', ['featured', 'top', 'homepage', 'premium_badge'])->default('featured');
            $table->decimal('price', 12, 2);
            $table->unsignedSmallInteger('duration_days')->default(30);
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('ad_placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('auth_users')->cascadeOnDelete();
            $table->foreignId('package_id')->nullable()->constrained('ad_packages')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->enum('status', ['reserved', 'active', 'paused', 'expired', 'cancelled'])->default('reserved');
            $table->decimal('credit_amount', 12, 2)->default(0.00);
            $table->string('admin_note', 500)->nullable();
            $table->timestamps();

            $table->index('property_id');
            $table->index('owner_id');
            $table->index('status');
            $table->index(['status', 'ends_at']);
        });

        Schema::create('ad_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('placement_id')->constrained('ad_placements')->cascadeOnDelete();
            $table->enum('event_type', ['impression', 'click', 'enquiry', 'application']);
            $table->timestamp('created_at')->nullable()->useCurrent();

            $table->index('placement_id');
            $table->index(['placement_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_events');
        Schema::dropIfExists('ad_placements');
        Schema::dropIfExists('ad_packages');
    }
};