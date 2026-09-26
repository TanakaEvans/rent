<?php

namespace App\Http\Controllers;

use App\Models\IdentityDocument;
use App\Models\TenantProfile;
use App\Services\TenantProfileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TenantProfileController extends Controller
{
    public function __construct(private readonly TenantProfileService $profiles)
    {
    }

    /**
     * The tenant's own profile + identity-evidence page.
     */
    public function show()
    {
        $user = auth()->user();

        return Inertia::render('Tenant/Profile', [
            'profile' => $this->profiles->profileFor($user),
            'documents' => $this->profiles->documentsFor($user),
            'kyc_tier' => $this->profiles->kycTierFor($user),
            'badge_tier' => $this->profiles->badgeTierFor($user),
            'options' => [
                'employment_status' => $this->options(TenantProfile::EMPLOYMENT_STATUSES, [
                    'employed' => 'Employed',
                    'self_employed' => 'Self-employed',
                    'student' => 'Student',
                    'unemployed' => 'Unemployed',
                    'retired' => 'Retired',
                ]),
                'salary_band' => $this->options(TenantProfile::SALARY_BANDS, [
                    'under_250' => 'Under $250',
                    '250_to_500' => '$250 – $500',
                    '501_to_1000' => '$501 – $1,000',
                    '1000_to_2000' => '$1,001 – $2,000',
                    'over_2000' => 'Over $2,000',
                ]),
                'preferred_contact' => $this->options(TenantProfile::PREFERRED_CONTACTS, [
                    'platform' => 'In-app messages',
                    'phone' => 'Phone call',
                    'whatsapp' => 'WhatsApp',
                ]),
                'kyc_types' => $this->options(IdentityDocument::KYC_TYPES, [
                    'national_id' => 'National ID card (physical)',
                    'driving_licence' => 'Driving licence',
                ]),
            ],
        ]);
    }

    /**
     * Save the tenant's profile fields (nullable fields deliberately allow
     * clearing a value).
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'phone' => ['nullable', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:100'],
            'employment_status' => ['nullable', Rule::in(TenantProfile::EMPLOYMENT_STATUSES)],
            'salary_band' => ['nullable', Rule::in(TenantProfile::SALARY_BANDS)],
            'preferred_contact' => ['nullable', Rule::in(TenantProfile::PREFERRED_CONTACTS)],
            'about' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->profiles->updateProfile($request->user(), $validated);

        return redirect()->route('tenant.profile')
            ->with('success', 'Profile saved.');
    }

    /**
     * Upload (or replace) one piece of KYC evidence for the tenant.
     */
    public function storeDocument(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(IdentityDocument::KYC_TYPES)],
            'document' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ]);

        $this->profiles->uploadDocument($request->user(), $validated['document'], $validated['type']);

        return redirect()->route('tenant.profile')
            ->with('success', 'Identity document uploaded and queued for review.');
    }

    /**
     * The tenant deletes their own evidence (the audit trail is kept).
     */
    public function destroyDocument(Request $request, IdentityDocument $document)
    {
        $this->profiles->removeDocument($request->user(), $document);

        return redirect()->route('tenant.profile')
            ->with('success', 'Identity document removed.');
    }

    /**
     * Stream the private-disk file back to the tenant who uploaded it (or an
     * authorized Admin/Superuser reviewing evidence later — S5).
     */
    public function downloadDocument(Request $request, IdentityDocument $document)
    {
        $document = $this->profiles->authorize($request->user(), $document->id);

        return Storage::disk(TenantProfileService::PRIVATE_DISK)
            ->download($document->file_path, $document->original_name, ['Content-Type' => $document->mime]);
    }

    /**
     * Shape a catalogue into [{key, label}] for the form.
     */
    private function options(array $keys, array $labels): array
    {
        return collect($keys)->map(fn (string $key) => ['key' => $key, 'label' => $labels[$key] ?? ucwords(str_replace('_', ' ', $key))])->values()->all();
    }
}