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
        Schema::create('lease_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->constrained('leases')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('auth_users')->cascadeOnDelete();
            $table->timestamp('signed_at')->nullable();
            $table->string('signature_payload', 255)->nullable();
            $table->timestamps();

            $table->unique(['lease_id', 'user_id']);
            $table->index(['lease_id', 'signed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lease_signatures');
    }
};