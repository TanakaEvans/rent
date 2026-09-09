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
        Schema::create('viewing_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('auth_users')->cascadeOnDelete();
            $table->foreignId('slot_id')->constrained('viewing_slots')->restrictOnDelete();
            $table->string('request_message', 1000)->nullable();
            $table->string('status', 20)->default('requested');
            $table->string('outcome', 255)->nullable();
            $table->timestamps();

            $table->index(['property_id', 'status']);
            $table->index(['tenant_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('viewing_requests');
    }
};