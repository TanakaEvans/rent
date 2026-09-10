<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->nullable()->constrained('leases')->cascadeOnDelete();
            $table->string('type')->default('lease_agreement');
            $table->string('name');
            $table->longText('content');
            $table->string('mime')->default('text/plain');
            $table->unsignedBigInteger('size')->nullable();
            $table->unsignedSmallInteger('version')->default(1);
            $table->string('visibility')->default('private');
            $table->timestamps();

            $table->index(['lease_id', 'version']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};