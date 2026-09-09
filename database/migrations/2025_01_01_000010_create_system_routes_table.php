<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('system_module_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('name')->unique();
            $table->string('uri')->nullable();
            $table->string('description')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_routes');
    }
};
