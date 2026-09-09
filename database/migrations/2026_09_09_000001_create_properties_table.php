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
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('auth_users')->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->enum('property_type', ['house', 'flat', 'townhouse', 'cottage', 'room', 'commercial', 'land'])
                ->default('house');
            $table->unsignedTinyInteger('bedrooms')->default(1);
            $table->unsignedTinyInteger('bathrooms')->default(1);
            $table->decimal('price', 12, 2);
            $table->decimal('deposit', 12, 2)->nullable();
            $table->boolean('furnished')->default(false);
            $table->enum('status', ['available', 'reserved', 'occupied', 'unavailable'])
                ->default('available');
            $table->string('suburb', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('address', 255)->nullable();
            $table->json('amenities')->nullable();
            $table->string('cover_image', 255)->nullable();
            $table->boolean('featured')->default(false);
            $table->boolean('verified')->default(false);
            $table->date('available_from')->nullable();
            $table->timestamps();

            $table->index(['status', 'city']);
            $table->index(['owner_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};