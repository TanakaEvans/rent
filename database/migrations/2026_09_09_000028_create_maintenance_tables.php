<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Maintenance report & triage (Module 10, Wave 5 slice 1).
     *
     * maintenance_requests is the tenant-reported repair queue with a unique
     * MR number, an explicit state machine (reported -> assigned ->
     * in_progress -> completed -> closed | declined) and an SLA clock
     * (sla_due_at from the priority rules); escalated_at is set by the
     * daily maintenance:escalate sweep when the first-response SLA is
     * breached. maintenance_actions is the immutable timeline every state
     * change appends to (reported, escalated, acknowledged, ...).
     */
    public function up(): void
    {
        Schema::create('maintenance_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_no', 20)->unique();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('auth_users')->cascadeOnDelete();
            $table->enum('category', ['plumbing', 'electrical', 'appliance', 'structural', 'pest', 'safety', 'other']);
            $table->enum('priority', ['low', 'medium', 'high', 'emergency']);
            $table->string('title', 120);
            $table->text('description');
            $table->enum('status', ['reported', 'assigned', 'in_progress', 'completed', 'closed', 'declined'])->default('reported');
            $table->timestamp('sla_due_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamps();

            $table->index('property_id');
            $table->index('tenant_id');
            $table->index('status');
            $table->index(['status', 'sla_due_at']);
        });

        Schema::create('maintenance_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('maintenance_requests')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('auth_users')->nullOnDelete();
            $table->string('action', 40);
            $table->string('notes', 500)->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();

            $table->index('request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_actions');
        Schema::dropIfExists('maintenance_requests');
    }
};