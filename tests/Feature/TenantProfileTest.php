<?php

namespace Tests\Feature;

use App\Models\IdentityDocument;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TenantProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function tenant(): User
    {
        return User::where('username', 'tenant')->firstOrFail();
    }

    private function jwtTenant(): User
    {
        $other = User::factory()->create(['name' => 'JWT Tenant', 'password_changed_at' => now()]);
        $other->roles()->attach(Role::where('name', 'Tenant')->first()->id);

        return $other;
    }

    // ---------- profile ----------

    public function test_tenant_can_view_their_profile_page(): void
    {
        $this->actingAs($this->tenant())
            ->get(route('tenant.profile'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tenant/Profile')
                ->where('profile.phone', '+263 771 234 567')
                ->has('options.kyc_types', 2)
                ->has('options.employment_status', 5)
                ->has('options.salary_band', 5)
                ->has('options.preferred_contact', 3));
    }

    public function test_tenant_can_update_their_profile(): void
    {
        $this->actingAs($this->tenant())
            ->put(route('tenant.profile'), [
                'phone' => '+263 712 987 654',
                'city' => 'Bulawayo',
                'employment_status' => 'self_employed',
                'salary_band' => '1000_to_2000',
                'preferred_contact' => 'whatsapp',
                'about' => 'Freelance designer, three-year tenant history.',
            ])
            ->assertRedirect(route('tenant.profile'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('tenant_profiles', [
            'user_id' => $this->tenant()->id,
            'phone' => '+263 712 987 654',
            'city' => 'Bulawayo',
            'employment_status' => 'self_employed',
            'salary_band' => '1000_to_2000',
            'preferred_contact' => 'whatsapp',
            'about' => 'Freelance designer, three-year tenant history.',
        ]);
    }

    public function test_tenant_can_clear_profile_fields(): void
    {
        $this->actingAs($this->tenant())
            ->put(route('tenant.profile'), [
                'phone' => '',
                'employment_status' => '',
                'salary_band' => '',
                'preferred_contact' => '',
            ])
            ->assertRedirect(route('tenant.profile'));

        $this->assertDatabaseHas('tenant_profiles', [
            'user_id' => $this->tenant()->id,
            'phone' => null,
            'employment_status' => null,
            'salary_band' => null,
            'preferred_contact' => null,
        ]);
    }

    public function test_profile_is_created_when_it_does_not_exist_yet(): void
    {
        $tenant = $this->jwtTenant();

        $this->actingAs($tenant)
            ->put(route('tenant.profile'), [
                'phone' => '+263 773 111 222',
                'city' => 'Mutare',
                'employment_status' => 'student',
            ])
            ->assertRedirect(route('tenant.profile'));

        $this->assertDatabaseHas('tenant_profiles', [
            'user_id' => $tenant->id,
            'phone' => '+263 773 111 222',
            'city' => 'Mutare',
            'employment_status' => 'student',
        ]);
    }

    public function test_profile_rejects_unknown_catalogue_values(): void
    {
        $this->actingAs($this->tenant())
            ->put(route('tenant.profile'), [
                'employment_status' => 'ceo',
                'salary_band' => 'unlimited',
                'preferred_contact' => 'carrier_pigeon',
            ])
            ->assertSessionHasErrors(['employment_status', 'salary_band', 'preferred_contact']);

        $this->assertDatabaseHas('tenant_profiles', [
            'user_id' => $this->tenant()->id,
            'employment_status' => 'employed',
        ]);
    }

    // ---------- KYC identity evidence ----------

    public function test_tenant_can_upload_kyc_evidence_to_the_private_disk(): void
    {
        Storage::fake('local');

        $tenant = $this->tenant();
        $this->actingAs($tenant)
            ->post(route('tenant.profile.documents.store'), [
                'type' => 'national_id',
                'document' => UploadedFile::fake()->image('id.jpg'),
            ])
            ->assertRedirect(route('tenant.profile'));

        $document = IdentityDocument::where('user_id', $tenant->id)->first();
        $this->assertNotNull($document);
        $this->assertSame('national_id', $document->type);
        $this->assertSame('pending', $document->status);
        $this->assertStringStartsWith('kyc/'.$tenant->id.'/national_id-', $document->file_path);

        Storage::disk('local')->assertExists($document->file_path);
        $this->assertDatabaseHas('identity_document_audits', [
            'user_id' => $tenant->id,
            'document_id' => $document->id,
            'action' => 'uploaded',
            'actor_id' => $tenant->id,
        ]);
    }

    public function test_uploading_the_same_type_replaces_the_file_and_audits_it(): void
    {
        Storage::fake('local');

        $tenant = $this->tenant();
        $this->actingAs($tenant)->post(route('tenant.profile.documents.store'), [
            'type' => 'driving_licence',
            'document' => UploadedFile::fake()->image('licence-before.jpg'),
        ]);
        $first = IdentityDocument::where('user_id', $tenant->id)->where('type', 'driving_licence')->firstOrFail();

        $this->actingAs($tenant)->post(route('tenant.profile.documents.store'), [
            'type' => 'driving_licence',
            'document' => UploadedFile::fake()->image('licence-after.jpg'),
        ])->assertRedirect(route('tenant.profile'));

        $this->assertSame(1, IdentityDocument::where('user_id', $tenant->id)->where('type', 'driving_licence')->count());
        $row = IdentityDocument::where('user_id', $tenant->id)->where('type', 'driving_licence')->firstOrFail();
        $this->assertSame($first->id, $row->id);
        $this->assertNotSame($first->file_path, $row->file_path);
        Storage::disk('local')->assertMissing($first->file_path);
        Storage::disk('local')->assertExists($row->file_path);

        $this->assertDatabaseHas('identity_document_audits', [
            'user_id' => $tenant->id,
            'document_id' => $row->id,
            'action' => 'replaced',
        ]);
    }

    public function test_kyc_upload_rejects_unknown_types_and_bad_files(): void
    {
        Storage::fake('local');

        $this->actingAs($this->tenant())
            ->post(route('tenant.profile.documents.store'), [
                'type' => 'passport',
                'document' => UploadedFile::fake()->image('passport.jpg'),
            ])
            ->assertSessionHasErrors('type');

        $this->actingAs($this->tenant())
            ->post(route('tenant.profile.documents.store'), [
                'type' => 'national_id',
                'document' => UploadedFile::fake()->create('evil.txt', 50, 'text/plain'),
            ])
            ->assertSessionHasErrors('document');

        $s4SeedScope = IdentityDocument::where('user_id', $this->tenant()->id);
        $this->assertSame(0, $s4SeedScope->count());
    }

    public function test_tenant_can_download_their_own_document(): void
    {
        Storage::fake('local');

        $tenant = $this->tenant();
        $this->actingAs($tenant)->post(route('tenant.profile.documents.store'), [
            'type' => 'national_id',
            'document' => UploadedFile::fake()->image('id.jpg'),
        ]);
        $document = IdentityDocument::where('user_id', $tenant->id)->firstOrFail();

        $this->actingAs($tenant)
            ->get(route('tenant.profile.documents.download', ['document' => $document->id]))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=id.jpg')
            ->assertHeader('content-type', 'image/jpeg');
    }

    public function test_a_tenant_cannot_see_another_tenants_documents(): void
    {
        Storage::fake('local');

        $other = $this->jwtTenant();
        $this->actingAs($other)->post(route('tenant.profile.documents.store'), [
            'type' => 'national_id',
            'document' => UploadedFile::fake()->image('other-id.jpg'),
        ]);
        $document = IdentityDocument::where('user_id', $other->id)->firstOrFail();

        $tenant = $this->tenant();
        $this->actingAs($tenant)
            ->get(route('tenant.profile.documents.download', ['document' => $document->id]))
            ->assertNotFound();
        $this->actingAs($tenant)
            ->delete(route('tenant.profile.documents.destroy', ['document' => $document->id]))
            ->assertNotFound();

        $this->assertDatabaseHas('identity_documents', ['id' => $document->id]);
        Storage::disk('local')->assertExists($document->file_path);
    }

    public function test_removing_a_document_deletes_the_file_but_keeps_the_audit_trail(): void
    {
        Storage::fake('local');

        $tenant = $this->tenant();
        $this->actingAs($tenant)->post(route('tenant.profile.documents.store'), [
            'type' => 'driving_licence',
            'document' => UploadedFile::fake()->image('licence.jpg'),
        ]);
        $document = IdentityDocument::where('user_id', $tenant->id)->firstOrFail();

        $this->actingAs($tenant)
            ->delete(route('tenant.profile.documents.destroy', ['document' => $document->id]))
            ->assertRedirect(route('tenant.profile'));

        $this->assertNull(IdentityDocument::where('id', $document->id)->first());
        Storage::disk('local')->assertMissing($document->file_path);
        $this->assertDatabaseHas('identity_document_audits', [
            'user_id' => $tenant->id,
            'action' => 'removed',
            'actor_id' => $tenant->id,
        ]);
    }

    public function test_owner_cannot_manage_another_users_tenant_profile(): void
    {
        Storage::fake('local');

        $owner = User::where('username', 'owner')->firstOrFail();
        $tenant = $this->tenant();

        $this->actingAs($owner)
            ->get(route('tenant.profile'))
            ->assertForbidden();

        $this->actingAs($owner)
            ->put(route('tenant.profile'), ['phone' => '0711 000 000'])
            ->assertForbidden();

        $this->actingAs($owner)
            ->post(route('tenant.profile.documents.store'), [
                'type' => 'national_id',
                'document' => UploadedFile::fake()->image('owner-id.jpg'),
            ])
            ->assertForbidden();

        $document = IdentityDocument::create([
            'user_id' => $tenant->id,
            'type' => 'national_id',
            'file_path' => 'kyc/'.$tenant->id.'/national_id-owner-test.jpg',
            'original_name' => 'tenant-id.jpg',
            'mime' => 'image/jpeg',
            'size' => 1024,
            'status' => 'pending',
        ]);
        Storage::disk('local')->put($document->file_path, 'x');

        $this->actingAs($owner)
            ->delete(route('tenant.profile.documents.destroy', ['document' => $document->id]))
            ->assertForbidden();
        $this->actingAs($owner)
            ->get(route('tenant.profile.documents.download', ['document' => $document->id]))
            ->assertForbidden();
    }
}