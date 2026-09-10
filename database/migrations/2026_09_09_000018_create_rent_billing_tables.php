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
        Schema::create('rent_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->constrained('leases')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('rent_amount', 12, 2);
            $table->json('payment_terms')->nullable();
            $table->timestamps();

            $table->unique('lease_id');
        });

        Schema::create('rent_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('auth_users')->cascadeOnDelete();
            $table->foreignId('lease_id')->constrained('leases')->cascadeOnDelete();
            $table->foreignId('schedule_id')->constrained('rent_schedules')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('amount', 12, 2);
            $table->enum('status', ['draft', 'due', 'paid', 'overdue', 'cancelled', 'refunded'])
                ->default('draft');
            $table->string('invoice_no')->unique();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();

            $table->index(['lease_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['period_start', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rent_invoices');
        Schema::dropIfExists('rent_schedules');
    }
};