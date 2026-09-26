<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preservation-Release 2.0 (S2): owner trust + reputation groundwork.
 *
 *  - `badge_tier` (none | bronze | silver | gold) is the KYC-derived tier
 *    column. Gold/silver/bronze badges on the marketplace derive from this
 *    field — the S5 approve/revoke flow writes it; nothing renders tier
 *    badges from manual UI fluff.
 *  - `rating_avg` / `ratings_count` are the landlord-reputation feed for the
 *    "Top-rated" marketplace sort. S8 (viewing ratings) and S9 (net-tenancy
 *    scores) populate them through real event data; this slice only seeds
 *    demo values and consumes them in the search ordering.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auth_users', function (Blueprint $table) {
            $table->string('badge_tier', 20)->default('none');
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('ratings_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('auth_users', function (Blueprint $table) {
            $table->dropColumn(['badge_tier', 'rating_avg', 'ratings_count']);
        });
    }
};