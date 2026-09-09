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
        Schema::create('leases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('auth_users')->cascadeOnDelete();
            $table->foreignId('application_id')->nullable()->constrained('rental_applications')->nullOnDelete();
            $table->string('lease_no')->unique();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('rent_amount', 12, 2);
            $table->decimal('deposit_amount', 12, 2);
            $table->json('payment_terms')->nullable();
            $table->enum('status', ['draft', 'sent', 'signed', 'active', 'renewed', 'terminated'])
                ->default('draft');
            $table->unsignedSmallInteger('clause_version')->default(1);
            $table->timestamps();

            $table->index(['property_id']);
            $table->index(['tenant_id']);
            $table->index(['status']);
        });

        Schema::create('lease_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->constrained('leases')->cascadeOnDelete();
            $table->string('action');
            $table->foreignId('performed_by')->nullable()->constrained('auth_users')->nullOnDelete();
            $table->json('details')->nullable();
            $table->timestamps();

            $table->index(['lease_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lease_history');
        Schema::dropIfExists('leases');
    }
};