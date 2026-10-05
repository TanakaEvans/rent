<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-stated indicative date their rent money is available, given when
 * applying so the owner can vet affordability/timing (Kule batch 2, item 1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rental_applications', function (Blueprint $table) {
            $table->date('funds_available_from')->nullable()->after('message');
        });
    }

    public function down(): void
    {
        Schema::table('rental_applications', function (Blueprint $table) {
            $table->dropColumn('funds_available_from');
        });
    }
};
