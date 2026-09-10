<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Listing/user reports (Marketplace §35 anti-scam, Module 19). Reports
     * from the marketplace go into an admin moderation queue keyed by
     * `status`. `resolved_by`/`resolved_at`/`resolution_note` record the
     * moderation outcome.
     */
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->nullable()->constrained('auth_users')->nullOnDelete();
            $table->enum('subject_type', ['property', 'user']);
            $table->unsignedBigInteger('subject_id');
            $table->string('category', 100);
            $table->text('description');
            $table->enum('status', ['open', 'under_review', 'resolved', 'dismissed', 'escalated'])->default('open');
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->foreignId('resolved_by')->nullable()->constrained('auth_users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->index('status');
            $table->index(['reporter_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};