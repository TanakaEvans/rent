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
        Schema::create('enquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('auth_users')->cascadeOnDelete();
            $table->string('message', 1000);
            $table->string('phone', 20)->nullable();
            $table->string('reply', 1000)->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->string('status', 20)->default('new');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['property_id', 'tenant_id']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enquiries');
    }
};