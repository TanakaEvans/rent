<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('auth_user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('auth_users')->onDelete('cascade');
            $table->foreignId('role_id')->constrained('auth_roles')->onDelete('cascade');
            $table->foreignId('assigned_by')->nullable()->constrained('auth_users')->onDelete('set null');
            $table->timestamps();
            
            $table->unique(['user_id', 'role_id']);
            $table->index(['user_id', 'role_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auth_user_roles');
    }
};
