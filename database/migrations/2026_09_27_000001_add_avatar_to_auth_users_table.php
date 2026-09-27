<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Profile photo for every account (owner, tenant, admin, contractor).
 * Stored on the public disk; shown in signed-in areas only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auth_users', function (Blueprint $table) {
            $table->string('avatar_path')->nullable()->after('username');
        });
    }

    public function down(): void
    {
        Schema::table('auth_users', function (Blueprint $table) {
            $table->dropColumn('avatar_path');
        });
    }
};
