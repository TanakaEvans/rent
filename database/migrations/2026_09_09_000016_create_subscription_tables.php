<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedSmallInteger('listing_limit')->nullable()->comment('null = unlimited');
            $table->unsignedSmallInteger('featured_slots')->default(0);
            $table->string('support_tier')->default('standard');
            $table->boolean('analytics_enabled')->default(false);
            $table->decimal('price', 12, 2)->default(0);
            $table->string('billing_cycle', 10)->default('monthly');
            $table->string('status', 20)->default('active')->comment('active/archived');
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_id');
            $table->unsignedBigInteger('plan_id');
            $table->string('status', 20)->default('active')->comment('active/grace/suspended/cancelled');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('cycle', 10)->default('monthly');
            $table->json('details')->nullable();
            $table->timestamps();

            $table->foreign('owner_id')->references('id')->on('auth_users')->cascadeOnDelete();
            $table->foreign('plan_id')->references('id')->on('subscription_plans')->restrictOnDelete();
            $table->index(['owner_id', 'status']);
        });

        Schema::create('subscription_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subscription_id');
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('status', 20)->default('pending')->comment('pending/paid/cancelled/refunded');
            $table->string('invoice_no', 40)->unique();
            $table->string('receipt_no', 40)->unique()->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('details')->nullable();
            $table->timestamps();

            $table->foreign('subscription_id')->references('id')->on('subscriptions')->cascadeOnDelete();
            $table->index('status');
        });

        Schema::create('subscription_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subscription_id');
            $table->string('event');
            $table->json('details')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('subscription_id')->references('id')->on('subscriptions')->cascadeOnDelete();
            $table->index(['subscription_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_history');
        Schema::dropIfExists('subscription_invoices');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('subscription_plans');
    }
};