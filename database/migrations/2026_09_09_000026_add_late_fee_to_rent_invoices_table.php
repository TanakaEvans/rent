<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Overdue rent invoices accrue a documented late charge (Module 09,
     * Wave 4 slice 5, FR-06). The charge is always stored as a settled
     * `late_fee` amount and recomputed deterministically from the
     * `late_fees.*` configuration by `RentService::accrueLateFees`, so the
     * figure never drifts from the rules that produced it.
     */
    public function up(): void
    {
        Schema::table('rent_invoices', function (Blueprint $table) {
            $table->decimal('late_fee', 12, 2)->default(0)->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('rent_invoices', function (Blueprint $table) {
            $table->dropColumn('late_fee');
        });
    }
};