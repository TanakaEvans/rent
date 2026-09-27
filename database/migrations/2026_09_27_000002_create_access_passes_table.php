<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Access passes back the configurable free-then-paid access model (Module 24 —
 * `access.*`). A pass is a paid grant of platform access for the configured
 * period; it exists only once charging is switched on and the free window has
 * ended. While `access.charge_enabled` is false no pass is ever required, so
 * this table simply stays empty and nothing changes for anyone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_passes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency', 10)->default('USD');
            $table->string('reference', 60)->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('auth_users')->cascadeOnDelete();
            $table->index(['user_id', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_passes');
    }
};
