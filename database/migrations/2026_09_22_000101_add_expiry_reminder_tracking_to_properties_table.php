<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Listing expiry reminders are sent once per threshold per expiry cycle.
 * `expiry_reminder_sent_days` records the tightest threshold (e.g. 14/7/1)
 * already sent for the current `expires_at`; it is cleared whenever a new
 * validity window is stamped (publish, relist, renew).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->unsignedSmallInteger('expiry_reminder_sent_days')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('expiry_reminder_sent_days');
        });
    }
};
