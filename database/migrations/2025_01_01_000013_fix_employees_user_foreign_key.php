<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Drop the incorrect foreign key constraint
            $table->dropForeign(['user_id']);
            
            // Add the correct foreign key constraint pointing to auth_users
            $table->foreign('user_id')->references('id')->on('auth_users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }
};
