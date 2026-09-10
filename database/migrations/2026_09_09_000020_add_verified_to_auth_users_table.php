<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Owner identity verification (Marketplace §9 "Verified Owner", §36).
     * `verified` marks an owner whose identity/details Dzimba has confirmed;
     * `verified_at` records when. Property-level verification already lives
     * on `properties.verified`; this is the separate owner-level signal.
     */
    public function up(): void
    {
        Schema::table('auth_users', function (Blueprint $table) {
            $table->boolean('verified')->default(false);
            $table->timestamp('verified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('auth_users', function (Blueprint $table) {
            $table->dropColumn(['verified', 'verified_at']);
        });
    }
};