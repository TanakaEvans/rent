<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Contractor registry + maintenance assignment (Module 11, Wave 5 slice 2).
     *
     * contractors is the verified-tradesperson registry (user_id nullable — a
     * contractor is a registered user only when they hold a login; the profile
     * can stand alone). Status follows FR-02: unverified -> vetting -> verified
     * with verified <-> suspended moderation. maintenance_requests gains the
     * quoted cost (approved_quote) and the assigned contractor that the owner
     * commits to when the request moves reported -> assigned.
     */
    public function up(): void
    {
        Schema::create('contractors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('auth_users')->nullOnDelete();
            $table->string('business_name', 120);
            $table->string('contact', 120);
            $table->json('service_area');
            $table->enum('status', ['unverified', 'vetting', 'verified', 'suspended'])->default('unverified');
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('jobs_completed')->default(0);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
        });

        Schema::create('contractor_trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contractor_id')->constrained('contractors')->cascadeOnDelete();
            $table->string('trade', 60);
            $table->decimal('rate', 10, 2)->nullable();
            $table->timestamps();

            $table->index('contractor_id');
        });

        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->decimal('approved_quote', 12, 2)->nullable()->after('description');
            $table->foreignId('assigned_contractor_id')->nullable()
                ->after('approved_quote')
                ->constrained('contractors')
                ->nullOnDelete();
            $table->index('assigned_contractor_id');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->dropForeign(['assigned_contractor_id']);
            $table->dropIndex(['assigned_contractor_id']);
            $table->dropColumn(['approved_quote', 'assigned_contractor_id']);
        });

        Schema::dropIfExists('contractor_trades');
        Schema::dropIfExists('contractors');
    }
};