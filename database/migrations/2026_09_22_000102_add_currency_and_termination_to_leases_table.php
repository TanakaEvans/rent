<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leases carry the listing currency they were agreed in, and an owner
 * termination records the actual end date and reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            $table->string('currency', 3)->default('USD')->after('deposit_amount');
            $table->date('terminated_on')->nullable()->after('status');
            $table->string('termination_reason', 1000)->nullable()->after('terminated_on');
        });
    }

    public function down(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            $table->dropColumn(['currency', 'terminated_on', 'termination_reason']);
        });
    }
};
