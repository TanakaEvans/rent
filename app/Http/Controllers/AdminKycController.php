<?php

namespace App\Http\Controllers;

use App\Models\IdentityDocument;
use App\Services\TenantProfileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AdminKycController extends Controller
{
    public function __construct(private readonly TenantProfileService $profiles)
    {
    }

    /**
     * The Admin/Superuser identity-review queue (S5). Rows are grouped on the
     * page by the current status filter; the counts always reflect the whole
     * queue so reviewers see what is waiting.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(IdentityDocument::STATUSES)],
        ]);

        $status = $validated['status'] ?? null;

        $counts = IdentityDocument::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return Inertia::render('Admin/Kyc/Index', [
            'rows' => $this->profiles->listForAdmin($status),
            'activeStatus' => $status,
            'counts' => [
                'pending' => (int) ($counts['pending'] ?? 0),
                'approved' => (int) ($counts['approved'] ?? 0),
                'rejected' => (int) ($counts['rejected'] ?? 0),
            ],
            'options' => [
                'statuses' => [
                    ['key' => 'pending', 'label' => 'Pending'],
                    ['key' => 'approved', 'label' => 'Approved'],
                    ['key' => 'rejected', 'label' => 'Rejected'],
                ],
                'kyc_types' => collect(IdentityDocument::KYC_TYPES)->map(fn (string $key) => [
                    'key' => $key,
                    'label' => $key === 'national_id' ? 'National ID card (physical)' : 'Driving licence',
                ])->values()->all(),
                'tiers' => [
                    ['key' => 'none', 'label' => 'Not verified'],
                    ['key' => 'basic', 'label' => 'Basic (1 document)'],
                    ['key' => 'full', 'label' => 'Full (both documents)'],
                ],
            ],
        ]);
    }

    /**
     * pending → approved. The tenant's badge tier is recomputed (silver for
     * the first approved document, gold once both types are approved).
     */
    public function approve(Request $request, IdentityDocument $document)
    {
        $this->profiles->approve($request->user(), $document);

        return back()->with('success', 'Identity document approved.');
    }

    /**
     * pending → rejected with an optional reviewer note.
     */
    public function reject(Request $request, IdentityDocument $document)
    {
        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->profiles->reject($request->user(), $document, $validated['note'] ?? null);

        return back()->with('success', 'Identity document rejected.');
    }

    /**
     * approved → rejected: a badge tied to this document no longer stands.
     */
    public function revoke(Request $request, IdentityDocument $document)
    {
        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->profiles->revoke($request->user(), $document, $validated['note'] ?? null);

        return back()->with('success', 'Identity document revoked.');
    }

    /**
     * Stream a private-disk scan back to an Admin/Superuser reviewer. Seeded
     * metadata-only demo rows have no stored scan and 404 here.
     */
    public function download(Request $request, IdentityDocument $document)
    {
        $document = $this->profiles->authorize($request->user(), $document->id);

        $disk = Storage::disk(TenantProfileService::PRIVATE_DISK);
        if (! $disk->exists($document->file_path)) {
            abort(404, 'The stored scan is no longer available.');
        }

        return $disk->download($document->file_path, $document->original_name, ['Content-Type' => $document->mime]);
    }
}