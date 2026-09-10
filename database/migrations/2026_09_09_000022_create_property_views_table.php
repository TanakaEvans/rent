<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Property view tracking (Marketplace §43 analytics, §46 recently viewed,
     * §9 "Popular" badge). One row per detail-page open; `user_id` is set for
     * logged-in visitors so per-tenant "recently viewed" works, and an IP is
     * kept for anonymous counting. Guests are deduplicated lazily at render.
     */
    public function up(): void
    {
        Schema::create('property_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('auth_users')->nullOnDelete();
            $table->string('ip', 45)->nullable();
            $table->timestamp('viewed_at');
            $table->timestamps();

            $table->index(['property_id', 'viewed_at']);
            $table->index(['user_id', 'viewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_views');
    }
};