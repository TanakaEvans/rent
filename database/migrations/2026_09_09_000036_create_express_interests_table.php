<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Express-Interest queue (S6 — Presentation Release 2.0): the
     * "mass-enquiry" building block. A tenant taps Express Interest on an
     * available listing instead of composing a full enquiry; the owner keeps
     * one lightweight queue per portfolio. One row per tenant×property
     * (re-express re-opens an archived row instead of duplicating).
     */
    public function up(): void
    {
        Schema::create('express_interests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('auth_users')->cascadeOnDelete();
            $table->string('note', 500)->nullable();
            $table->string('status', 20)->default('interested');
            $table->timestamps();

            $table->unique(['property_id', 'tenant_id']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('express_interests');
    }
};