<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_routes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('role_id');
            $table->foreignId('system_route_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->foreign('role_id')->references('id')->on('auth_roles')->onDelete('cascade');
            $table->unique(['role_id', 'system_route_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_routes');
    }
};
