<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * In-app chat conversations. Two kinds:
 *  - `direct`  — a tenant and a property owner talking about one property.
 *  - `support` — a user talking to ZimRent support (staff/admin join in).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('type', 10)->default('direct');
            $table->foreignId('property_id')->nullable()->constrained('properties')->nullOnDelete();
            $table->string('subject')->nullable();
            $table->timestamps();

            $table->index(['type', 'property_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
