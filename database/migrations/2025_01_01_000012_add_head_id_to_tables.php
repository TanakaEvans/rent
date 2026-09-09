<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add head_id to companies table
        Schema::table('companies', function (Blueprint $table) {
            $table->foreignId('head_id')->nullable()->after('status')->constrained('employees')->onDelete('set null');
        });

        // Add head_id to branches table (replacing manager_name/manager_phone)
        Schema::table('branches', function (Blueprint $table) {
            $table->foreignId('head_id')->nullable()->after('is_main_branch')->constrained('employees')->onDelete('set null');
        });

        // Add head_id to departments table (replacing head_name)
        Schema::table('departments', function (Blueprint $table) {
            $table->foreignId('head_id')->nullable()->after('description')->constrained('employees')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropForeign(['head_id']);
            $table->dropColumn('head_id');
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->dropForeign(['head_id']);
            $table->dropColumn('head_id');
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['head_id']);
            $table->dropColumn('head_id');
        });
    }
};
