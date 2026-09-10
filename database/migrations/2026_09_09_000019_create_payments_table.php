<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payments settle rent invoices (Module 09, Wave 4 slice 4). Each payment
     * belongs to exactly one rent invoice; the ledger is immutable once
     * settled (a settled invoice can never be paid again). Bank/mobile
     * payments carry a proof-of-payment path and high-value payments (or any
     * POP) can sit `pending` staff approval before the invoice is settled.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('rent_invoices')->cascadeOnDelete();
            $table->foreignId('paid_by')->constrained('auth_users')->cascadeOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('auth_users')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->enum('method', ['cash', 'bank', 'mobile', 'online']);
            $table->string('reference', 255)->nullable();
            $table->string('pop_path', 255)->nullable();
            $table->string('receipt_no', 40)->nullable()->unique();
            $table->enum('status', ['pending', 'settled', 'rejected', 'refunded'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index('invoice_id');
            $table->index('paid_by');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};