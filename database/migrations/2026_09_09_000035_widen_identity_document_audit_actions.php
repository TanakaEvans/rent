<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preservation-Release 2.0 (S5): KYC review actions on the audit trail.
 *
 * S4 recorded only self-service events (uploaded/replaced/removed). S5 adds
 * the Admin/Superuser review decisions approved/rejected/revoked, so the
 * `action` column widens from an ENUM to a string(20) — forward compatible,
 * existing rows untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('identity_document_audits', function (Blueprint $table) {
            $table->string('action', 20)->change();
        });
    }

    public function down(): void
    {
        Schema::table('identity_document_audits', function (Blueprint $table) {
            $table->enum('action', ['uploaded', 'replaced', 'removed'])->change();
        });
    }
};