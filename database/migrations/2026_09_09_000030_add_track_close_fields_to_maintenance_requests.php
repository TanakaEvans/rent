<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Wave 5 slice 3 (track & close out): tenant confirmation and the resolved
     * timestamp complete the maintenance lifecycle (reported -> assigned ->
     * in_progress -> completed -> [tenant confirmed] -> closed).
     */
    public function up(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->timestamp('tenant_confirmed_at')->nullable()->after('assigned_contractor_id');
            $table->timestamp('resolved_at')->nullable()->after('tenant_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->dropColumn(['tenant_confirmed_at', 'resolved_at']);
        });
    }
};