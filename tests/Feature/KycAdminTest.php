<?php

namespace Tests\Feature;

use App\Models\IdentityDocument;
use App\Models\Role;
use App\Models\User;
use App\Notifications\KycDocumentStatusNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KycAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function reviewer(): User
    {
        return User::where('username', 'admin')->firstOrFail();
    }

    private function tenant(): User
    {
        return User::where('username', 'tenant')->firstOrFail();
    }

    private function extraTenant(): User
    {
        $other = User::factory()->create(['name' => 'KYC Applicant', 'password_changed_at' => now()]);
        $other->roles()->attach(Role::where('name', 'Tenant')->first()->id);

        return $other;
    }

    private function upload(User $user, string $type, string $name = 'scan.jpg'): IdentityDocument
    {
        Storage::fake('local');
        $this->actingAs($user)->post(route('tenant.profile.documents.store'), [
            'type' => $type,
            'document' => UploadedFile::fake()->image($name),
        ]);

        return IdentityDocument::where('user_id', $user->id)->where('type', $type)->firstOrFail();
    }

    private function pendingRow(User $user, string $type = 'national_id'): IdentityDocument
    {
        return IdentityDocument::create([
            'user_id' => $user->id,
            'type' => $type,
            'file_path' => 'kyc/'.$user->id.'/'.$type.'-test.jpg',
            'original_name' => 'test.jpg',
            'mime' => 'image/jpeg',
            'size' => 1024,
            'status' => 'pending',
        ]);
    }

    // ---------- the review queue ----------

    public function test_admin_sees_the_review_queue_with_tiers_counts_and_filters(): void
    {
        $applicant = $this->extraTenant();
        $this->pendingRow($applicant);

        $this->actingAs($this->reviewer())
            ->get(route('admin.kyc.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Kyc/Index')
                ->where('counts.pending', 1)
                ->where('counts.approved', 2) // the seeded demo owner gold pair
                ->has('rows.data', 3)
                ->has('options.kyc_types', 2)
                ->has('options.tiers', 3));

        $pending = IdentityDocument::where('user_id', $applicant->id)->firstOrFail();
        $this->actingAs($this->reviewer())
            ->get(route('admin.kyc.index', ['status' => 'pending']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('activeStatus', 'pending')
                ->has('rows.data', 1)
                ->where('rows.data.0.id', $pending->id)
                ->where('rows.data.0.kyc_tier', 'none')
                ->where('rows.data.0.user.name', 'KYC Applicant'));
    }

    // ---------- approve / reject / revoke decisions ----------

    public function test_approving_evidence_earns_silver_then_gold_and_notifies(): void
    {
        Notification::fake();

        $tenant = $this->extraTenant();
        $id = $this->upload($tenant, 'national_id', 'id.jpg');

        $this->assertSame('bronze', $tenant->fresh()->badge_tier, 'a pending upload earns the bronze slate');

        $this->actingAs($this->reviewer())->post(route('admin.kyc.approve', ['document' => $id->id]))->assertRedirect();

        $id->refresh();
        $this->assertSame('approved', $id->status);
        $this->assertSame('silver', $tenant->fresh()->badge_tier, 'one approved document earns silver');

        $this->assertDatabaseHas('identity_document_audits', [
            'document_id' => $id->id,
            'action' => 'approved',
            'actor_id' => $this->reviewer()->id,
        ]);

        Notification::assertSentTo($tenant, KycDocumentStatusNotification::class);

        $licence = $this->upload($tenant, 'driving_licence', 'licence.jpg');
        $this->actingAs($this->reviewer())->post(route('admin.kyc.approve', ['document' => $licence->id]))->assertRedirect();

        $this->assertSame('full', $this->app->make(\App\Services\TenantProfileService::class)->kycTierFor($tenant->fresh()));
        $this->assertSame('gold', $tenant->fresh()->badge_tier, 'both approved documents earn gold');
    }

    public function test_rejecting_with_a_note_keeps_the_trail_and_sends_the_reason(): void
    {
        $tenant = $this->extraTenant();
        $id = $this->upload($tenant, 'national_id', 'blurry.jpg');

        $this->actingAs($this->reviewer())
            ->post(route('admin.kyc.reject', ['document' => $id->id]), ['note' => 'Scan too blurry to read'])
            ->assertRedirect();

        $id->refresh();
        $this->assertSame('rejected', $id->status);
        $this->assertSame('none', $tenant->fresh()->badge_tier);

        $this->assertDatabaseHas('identity_document_audits', [
            'document_id' => $id->id,
            'action' => 'rejected',
            'actor_id' => $this->reviewer()->id,
            'details' => 'national_id — Rejected — Scan too blurry to read',
        ]);

        $notification = \Illuminate\Notifications\DatabaseNotification::query()
            ->where('notifiable_id', $tenant->id)
            ->first();
        $this->assertNotNull($notification);
        $payload = $notification->data;
        $this->assertStringContainsString('Scan too blurry to read', $payload['body']);
    }

    public function test_revoking_an_approved_document_drops_the_badge(): void
    {
        Notification::fake();

        $tenant = $this->extraTenant();
        $id = $this->upload($tenant, 'national_id', 'id.jpg');
        $this->actingAs($this->reviewer())->post(route('admin.kyc.approve', ['document' => $id->id]));
        $this->assertSame('silver', $tenant->fresh()->badge_tier);

        $this->actingAs($this->reviewer())
            ->post(route('admin.kyc.revoke', ['document' => $id->id]), ['note' => 'Ownership dispute'])
            ->assertRedirect();

        $id->refresh();
        $this->assertSame('rejected', $id->status);
        $this->assertSame('none', $tenant->fresh()->badge_tier, 'the badge ladder drops when evidence is revoked');

        $this->assertDatabaseHas('identity_document_audits', [
            'document_id' => $id->id,
            'action' => 'revoked',
            'actor_id' => $this->reviewer()->id,
        ]);

        Notification::assertSentTo($tenant, KycDocumentStatusNotification::class);
    }

    // ---------- the state machine ----------

    public function test_illegal_review_transitions_conflict(): void
    {
        $applicant = $this->extraTenant();

        $approved = IdentityDocument::create([
            'user_id' => $applicant->id,
            'type' => 'national_id',
            'file_path' => 'kyc/'.$applicant->id.'/national_id-approved.jpg',
            'original_name' => 'a.jpg',
            'mime' => 'image/jpeg',
            'size' => 1024,
            'status' => 'approved',
        ]);
        $rejected = $this->pendingRow($applicant, 'driving_licence');
        $rejected->update(['status' => 'rejected']);

        $this->actingAs($this->reviewer())
            ->post(route('admin.kyc.approve', ['document' => $approved->id]))
            ->assertStatus(409, 're-approving an approved document conflicts');
        $this->actingAs($this->reviewer())
            ->post(route('admin.kyc.revoke', ['document' => $rejected->id]))
            ->assertStatus(409, 'revoking a rejected document conflicts');
        $this->actingAs($this->reviewer())
            ->post(route('admin.kyc.approve', ['document' => $rejected->id]))
            ->assertStatus(409, 'approving a rejected document conflicts');

        $this->assertSame('approved', $approved->fresh()->status);
        $this->assertSame('rejected', $rejected->fresh()->status);
    }

    // ---------- downloads ----------

    public function test_admin_can_download_a_real_tenant_scan(): void
    {
        Storage::fake('local');

        $tenant = $this->extraTenant();
        $id = $this->upload($tenant, 'national_id', 'real-id.jpg');

        $this->actingAs($this->reviewer())
            ->get(route('admin.kyc.download', ['document' => $id->id]))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=real-id.jpg')
            ->assertHeader('content-type', 'image/jpeg');
    }

    public function test_downloading_seeded_metadata_rows_returns_not_found(): void
    {
        $owner = User::where('username', 'owner')->firstOrFail();
        $demoRow = IdentityDocument::where('user_id', $owner->id)->where('type', 'national_id')->firstOrFail();

        $this->actingAs($this->reviewer())
            ->get(route('admin.kyc.download', ['document' => $demoRow->id]))
            ->assertNotFound();
    }

    // ---------- negative paths ----------

    public function test_tenants_are_forbidden_from_the_review_flows(): void
    {
        $tenant = $this->extraTenant();
        $pending = $this->pendingRow($tenant);

        $this->actingAs($this->tenant())
            ->get(route('admin.kyc.index'))
            ->assertForbidden();
        $this->actingAs($this->tenant())
            ->post(route('admin.kyc.approve', ['document' => $pending->id]))
            ->assertForbidden();
        $this->actingAs($this->tenant())
            ->post(route('admin.kyc.reject', ['document' => $pending->id]))
            ->assertForbidden();

        $this->assertSame('pending', $pending->fresh()->status);
    }
}