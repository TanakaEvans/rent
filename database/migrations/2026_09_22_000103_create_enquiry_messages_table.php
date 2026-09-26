<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only enquiry thread. The tenant's opening message stays on
 * `enquiries.message`; every later reply (owner or tenant) is one row here.
 * Existing single owner replies are carried over so no history is lost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enquiry_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enquiry_id')->constrained('enquiries')->cascadeOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('auth_users')->nullOnDelete();
            $table->string('sender_role', 10);
            $table->string('body', 1000);
            $table->timestamps();

            $table->index(['enquiry_id', 'created_at']);
        });

        DB::table('enquiries')
            ->join('properties', 'properties.id', '=', 'enquiries.property_id')
            ->whereNotNull('enquiries.reply')
            ->select('enquiries.id', 'enquiries.reply', 'enquiries.replied_at', 'enquiries.updated_at', 'properties.owner_id')
            ->orderBy('enquiries.id')
            ->chunk(500, function ($rows) {
                DB::table('enquiry_messages')->insert($rows->map(fn ($row) => [
                    'enquiry_id' => $row->id,
                    'sender_id' => $row->owner_id,
                    'sender_role' => 'owner',
                    'body' => $row->reply,
                    'created_at' => $row->replied_at ?? $row->updated_at,
                    'updated_at' => $row->replied_at ?? $row->updated_at,
                ])->all());
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('enquiry_messages');
    }
};
