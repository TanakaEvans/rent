<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the self-referential link used by the renewal workflow: a renewal
     * lease points at the active lease it continues (FR-05).
     */
    public function up(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            $table->unsignedBigInteger('renewed_from_id')->nullable()->after('application_id');
            $table->foreign('renewed_from_id')->references('id')->on('leases')->nullOnDelete();
            $table->index('renewed_from_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            $table->dropForeign(['renewed_from_id']);
            $table->dropIndex(['renewed_from_id']);
            $table->dropColumn('renewed_from_id');
        });
    }
};