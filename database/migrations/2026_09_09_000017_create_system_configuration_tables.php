<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('group_name', 50)->index();
            $table->string('key', 100)->unique();
            $table->string('type', 20)->default('string')->comment('string/integer/boolean/decimal/json');
            $table->text('value')->nullable();
            $table->string('label');
            $table->string('description')->nullable();
            $table->string('risk', 20)->default('low')->comment('low/medium/high/critical');
            $table->boolean('is_editable')->default(true);
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('configuration_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('configuration_id')->nullable();
            $table->string('key');
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->string('reason')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('configuration_id')->references('id')->on('system_configurations')->nullOnDelete();
            $table->foreign('changed_by')->references('id')->on('auth_users')->nullOnDelete();
            $table->foreign('approved_by')->references('id')->on('auth_users')->nullOnDelete();
            $table->index(['key', 'created_at']);
        });

        Schema::create('subscription_features', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('label');
            $table->string('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('subscription_plan_features', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_id');
            $table->unsignedBigInteger('feature_id');

            $table->foreign('plan_id')->references('id')->on('subscription_plans')->cascadeOnDelete();
            $table->foreign('feature_id')->references('id')->on('subscription_features')->cascadeOnDelete();
            $table->unique(['plan_id', 'feature_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plan_features');
        Schema::dropIfExists('subscription_features');
        Schema::dropIfExists('configuration_audits');
        Schema::dropIfExists('system_configurations');
    }
};