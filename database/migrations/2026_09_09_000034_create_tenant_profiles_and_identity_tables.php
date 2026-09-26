<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preservation-Release 2.0 (S4): tenant profiles + know-your-customer (KYC)
 * identity evidence on the private disk with an audit trail.
 *
 *  - `tenant_profiles` — one row per tenant (fresh signups land with none and
 *    complete it on the My Profile page). Feeds net-tenancy scoring (S9) and
 *    the KYC review queue (S5).
 *  - `identity_documents` — the tenant's own identity evidence scans
 *    (national/physical ID card or driving licence). Files always live on the
 *    private `local` disk (`storage/app/private/kyc/{user_id}/`); the DB row
 *    only records the path + metadata, never the content. Unique per
 *    (user_id, type) — re-uploading a type replaces the file. Status is
 *    `pending` at upload; S5 adds the Admin/Superuser approve/revoke flow.
 *  - `identity_document_audits` — append-only who/what/when trail for every
 *    upload, replace and removal. `document_id` is nullOnDelete so the trail
 *    survives the evidence being deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('auth_users')->cascadeOnDelete();
            $table->string('phone', 30)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('employment_status', 40)->nullable();
            $table->string('salary_band', 40)->nullable();
            $table->string('preferred_contact', 40)->nullable();
            $table->text('about')->nullable();
            $table->timestamps();
        });

        Schema::create('identity_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('auth_users')->cascadeOnDelete();
            $table->enum('type', ['national_id', 'driving_licence']);
            $table->string('file_path', 255);
            $table->string('original_name', 255);
            $table->string('mime', 100);
            $table->unsignedInteger('size');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->unique(['user_id', 'type']);
            $table->timestamps();

            $table->index(['status', 'user_id']);
        });

        Schema::create('identity_document_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('auth_users')->cascadeOnDelete();
            $table->foreignId('document_id')->nullable()->constrained('identity_documents')->nullOnDelete();
            $table->enum('action', ['uploaded', 'replaced', 'removed']);
            $table->foreignId('actor_id')->nullable()->constrained('auth_users')->nullOnDelete();
            $table->string('details', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_document_audits');
        Schema::dropIfExists('identity_documents');
        Schema::dropIfExists('tenant_profiles');
    }
};