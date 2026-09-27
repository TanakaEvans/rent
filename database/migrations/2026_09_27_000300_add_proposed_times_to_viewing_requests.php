<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenants can now suggest their own viewing time when none of the owner's
 * open slots suit them. Such a request has no slot yet (slot_id null) and
 * carries the proposed window; accepting it creates the slot and locks it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('viewing_requests', function (Blueprint $table) {
            $table->foreignId('slot_id')->nullable()->change();
            $table->dateTime('proposed_starts_at')->nullable()->after('slot_id');
            $table->dateTime('proposed_ends_at')->nullable()->after('proposed_starts_at');
        });
    }

    public function down(): void
    {
        Schema::table('viewing_requests', function (Blueprint $table) {
            $table->dropColumn(['proposed_starts_at', 'proposed_ends_at']);
            $table->foreignId('slot_id')->nullable(false)->change();
        });
    }
};
