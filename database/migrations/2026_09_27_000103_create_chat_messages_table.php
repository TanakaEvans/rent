<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only chat messages. `sender_id` is nullable so a message survives
 * the deletion of its author (shown as "Former user"). The composite index
 * serves the unread lookup (messages in a conversation after a read cursor).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('auth_users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
