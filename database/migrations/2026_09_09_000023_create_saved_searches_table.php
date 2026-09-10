<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tenant saved searches (Marketplace §41/§47, Module 04 Phase 2).
     * `criteria` stores the normalized marketplace filter JSON; `notify` opts
     * the tenant into match alerts for that search.
     */
    public function up(): void
    {
        Schema::create('saved_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('auth_users')->cascadeOnDelete();
            $table->string('name', 150);
            $table->json('criteria');
            $table->boolean('notify')->default(false);
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_searches');
    }
};