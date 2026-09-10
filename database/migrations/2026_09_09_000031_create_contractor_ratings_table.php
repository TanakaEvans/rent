<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Contractor ratings (Module 11, Wave 5 slice 4).
     *
     * After a maintenance request closes, the property owner rates the
     * contractor behind the job (1-5 + optional note, FR-04). One rating per
     * request (unique request_id); ratings reflect completed jobs only
     * (AC-02) because a request can only be rated once it reached `closed`.
     * The aggregate rating_avg / jobs_completed on contractors are recomputed
     * server-side whenever a rating lands here.
     */
    public function up(): void
    {
        Schema::create('contractor_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->unique()->constrained('maintenance_requests')->cascadeOnDelete();
            $table->foreignId('contractor_id')->constrained('contractors')->cascadeOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('auth_users')->nullOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index('contractor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contractor_ratings');
    }
};