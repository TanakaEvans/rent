<?php

namespace App\Services;

use App\Models\IdentityDocument;
use App\Models\IdentityDocumentAudit;
use App\Models\TenantProfile;
use App\Models\User;
use App\Notifications\KycDocumentStatusNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TenantProfileService
{
    /**
     * KYC scans always live on the private `local` disk. The default disk is
     * `storage/app/private` — nothing under it is ever symlinked to public.
     */
    public const PRIVATE_DISK = 'local';

    /**
     * KYC tiers derived from the approved identity documents (S5). `full`
     * requires both accepted document types approved; `basic` one approved;
     * `none` otherwise. Badge tiers derive from this ladder — never a manual
     * admin toggle.
     */
    public const KYC_TIERS = ['none', 'basic', 'full'];

    /**
     * Resolve the tenant's row (null until they complete the profile).
     */
    public function profileFor(User $user): ?TenantProfile
    {
        return $user->tenantProfile;
    }

    /**
     * Create-or-update the tenant's own profile. Fields are the validated
     * whitelist from the controller; nullable catalogues stay validated
     * there so a tenant can deliberately clear a field.
     */
    public function updateProfile(User $user, array $data): TenantProfile
    {
        return TenantProfile::updateOrCreate(['user_id' => $user->id], $data);
    }

    /**
     * The tenant's evidence rows, newest first.
     */
    public function documentsFor(User $user): Collection
    {
        return $user->identityDocuments()->latest()->with('audits:id,document_id,action,actor_id,details,created_at')->get();
    }

    /**
     * The user's derived KYC tier from their approved identity evidence.
     */
    public function kycTierFor(User $user): string
    {
        $approvedTypes = array_values(array_unique(
            IdentityDocument::where('user_id', $user->id)->where('status', 'approved')->pluck('type')->all()
        ));

        if (count($approvedTypes) === count(IdentityDocument::KYC_TYPES)) {
            return 'full';
        }

        return $approvedTypes === [] ? 'none' : 'basic';
    }

    /**
     * The user's badge tier, derived from the KYC tier + verification
     * decisions (S5) — a computed ladder, never a manual toggle:
     *
     *   gold    KYC tier full (both documents approved)
     *   silver  KYC tier basic (one document approved)
     *   bronze  any evidence on file (pending/approved) waiting on a decision
     *   none    nothing submitted
     */
    public function badgeTierFor(User $user): string
    {
        $tier = $this->kycTierFor($user);

        if ($tier === 'full') {
            return 'gold';
        }

        if ($tier === 'basic') {
            return 'silver';
        }

        if ($user->identityDocuments()->whereIn('status', ['pending', 'approved'])->exists()) {
            return 'bronze';
        }

        return 'none';
    }

    /**
     * Persist the derived badge tier on the user row so marketplace carts,
     * owner cards and admin lists read a stored value (S2 eager-loads select
     * `badge_tier`). Re-run after every KYC mutation — self-service uploads,
     * removals and every review decision.
     */
    public function recomputeBadgeTier(User $user): string
    {
        $tier = $this->badgeTierFor($user);
        \Illuminate\Support\Facades\DB::table($user->getTable())
            ->where('id', $user->id)
            ->update(['badge_tier' => $tier]);

        return $tier;
    }

    /**
     * The review queue for Admin/Superuser (S5): every identity document,
     * newest first, optionally narrowed to one status. Each row carries the
     * document owner and their derived KYC tier so the reviewer sees the
     * whole picture at a glance.
     */
    public function listForAdmin(?string $status = null, int $perPage = 12): LengthAwarePaginator
    {
        return IdentityDocument::with('user:id,name,email,badge_tier')
            ->when($status && in_array($status, IdentityDocument::STATUSES, true), fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate($perPage)
            ->through(fn (IdentityDocument $document) => $this->reviewRow($document));
    }

    /**
     * Shape one queue row for the admin page.
     */
    public function reviewRow(IdentityDocument $document): array
    {
        return [
            'id' => $document->id,
            'type' => $document->type,
            'original_name' => $document->original_name,
            'mime' => $document->mime,
            'size' => $document->size,
            'status' => $document->status,
            'created_at' => $document->created_at?->toIso8601String(),
            'user' => $document->user?->only(['id', 'name', 'email', 'badge_tier']),
            'kyc_tier' => $document->user ? $this->kycTierFor($document->user) : 'none',
        ];
    }

    /**
     * Approve pending evidence (pending → approved). The tenant's badge is
     * recomputed — one approved document earns silver, both earn gold.
     */
    public function approve(User $actor, IdentityDocument $document): void
    {
        $document = $this->reviewable($actor, $document->id, 'approved');

        $document->update(['status' => 'approved']);
        $this->record($document, 'approved', $actor, 'Approved — identity evidence accepted');
        $document->user->notify(new KycDocumentStatusNotification('approved'));
        $this->recomputeBadgeTier($document->user);
    }

    /**
     * Reject pending evidence with an optional reviewer note (pending →
     * rejected). Rejected is terminal for that upload; the tenant re-uploads
     * to return to the queue (which resets the row to pending).
     */
    public function reject(User $actor, IdentityDocument $document, ?string $note = null): void
    {
        $document = $this->reviewable($actor, $document->id, 'rejected');

        $document->update(['status' => 'rejected']);
        $this->record($document, 'rejected', $actor, $this->note('Rejected', $note));
        $document->user->notify(new KycDocumentStatusNotification('rejected', $note));
        $this->recomputeBadgeTier($document->user);
    }

    /**
     * Revoke an approved document (approved → rejected) — e.g. a badge that
     * should no longer stand. The ladder drops accordingly.
     */
    public function revoke(User $actor, IdentityDocument $document, ?string $note = null): void
    {
        $document = $this->reviewable($actor, $document->id, 'rejected', expect: 'approved');

        $document->update(['status' => 'rejected']);
        $this->record($document, 'revoked', $actor, $this->note('Revoked', $note));
        $document->user->notify(new KycDocumentStatusNotification('revoked', $note));
        $this->recomputeBadgeTier($document->user);
    }

    /**
     * Upload or replace one evidence scan for a tenant. Files are stored
     * under `kyc/{user_id}/{type}-{uuid}.{ext}` on the private disk; the row
     * records metadata only. A further scan of the same type replaces the
     * previous file (unique user×type) and the status resets to `pending`.
     */
    public function uploadDocument(User $user, UploadedFile $file, string $type): IdentityDocument
    {
        $existing = IdentityDocument::where('user_id', $user->id)->where('type', $type)->first();
        $action = $existing ? 'replaced' : 'uploaded';

        $extension = $this->safeExtension($file);
        $path = 'kyc/'.$user->id.'/'.$type.'-'.Str::uuid().'.'.$extension;
        $file->storeAs(Str::beforeLast($path, '/'), Str::afterLast($path, '/'), self::PRIVATE_DISK);

        if ($existing) {
            $this->deleteStoredFile($existing->file_path);
            $existing->update([
                'file_path' => $path,
                'original_name' => Str::limit($file->getClientOriginalName(), 255, ''),
                'mime' => $file->getMimeType() ?? 'application/octet-stream',
                'size' => $file->getSize(),
                'status' => 'pending',
            ]);
            $document = $existing->fresh();
        } else {
            $document = IdentityDocument::create([
                'user_id' => $user->id,
                'type' => $type,
                'file_path' => $path,
                'original_name' => Str::limit($file->getClientOriginalName(), 255, ''),
                'mime' => $file->getMimeType() ?? 'application/octet-stream',
                'size' => $file->getSize(),
                'status' => 'pending',
            ]);
        }

        IdentityDocumentAudit::create([
            'user_id' => $user->id,
            'document_id' => $document->id,
            'action' => $action,
            'actor_id' => $user->id,
            'details' => $type.' — '.$document->original_name,
        ]);

        $this->recomputeBadgeTier($user);

        return $document;
    }

    /**
     * Resolve an evidence row for a requester. Only the owner of the evidence
     * (or an Admin/Superuser) ever sees it; everyone else gets a 404 so row
     * existence is never leaked.
     */
    public function authorize(User $user, int $id): IdentityDocument
    {
        $document = IdentityDocument::with(['user:id,name,email', 'audits'])->find($id);

        if (! $document || ($document->user_id !== $user->id && ! $user->hasAnyRole(['Admin', 'Superuser']))) {
            throw new NotFoundHttpException('Identity document not found.');
        }

        return $document;
    }

    /**
     * Delete a tenant's own evidence. The file is removed from the private
     * disk and the audit trail keeps the provenance (document_id is released
     * by nullOnDelete, but the details text preserves the record).
     */
    public function removeDocument(User $user, IdentityDocument $document): void
    {
        $document = $this->authorize($user, $document->id);

        IdentityDocumentAudit::create([
            'user_id' => $document->user_id,
            'document_id' => $document->id,
            'action' => 'removed',
            'actor_id' => $user->id,
            'details' => $document->type.' — '.$document->original_name,
        ]);

        $this->deleteStoredFile($document->file_path);
        $document->delete();

        $this->recomputeBadgeTier($document->user);
    }

    /**
     * Load a reviewable document for an Admin/Superuser actor and enforce the
     * review state machine (409 on an illegal transition — e.g. approving a
     * rejected row or re-approving an approved one; revoke only from approved).
     */
    private function reviewable(User $actor, int $id, string $to, string $expect = 'pending'): IdentityDocument
    {
        if (! $actor->hasAnyRole(['Admin', 'Superuser'])) {
            abort(403, 'You do not have permission to review identity documents.');
        }

        $document = IdentityDocument::with('user:id,name,email,badge_tier')->find($id);

        if (! $document) {
            throw new NotFoundHttpException('Identity document not found.');
        }

        if ($document->status !== $expect || ! $document->canTransitionTo($to)) {
            throw new HttpException(409, 'The identity document is not in a state that allows '.($to === 'rejected' ? 'this decision' : $to).'.');
        }

        return $document;
    }

    /**
     * Write a review decision to the append-only audit trail.
     */
    private function record(IdentityDocument $document, string $action, User $actor, string $details): void
    {
        IdentityDocumentAudit::create([
            'user_id' => $document->user_id,
            'document_id' => $document->id,
            'action' => $action,
            'actor_id' => $actor->id,
            'details' => $document->type.' — '.$details,
        ]);
    }

    private function note(string $verb, ?string $note): string
    {
        return $note ? $verb.' — '.$note : $verb;
    }

    private function deleteStoredFile(string $path): void
    {
        if ($path && \Illuminate\Support\Facades\Storage::disk(self::PRIVATE_DISK)->exists($path)) {
            \Illuminate\Support\Facades\Storage::disk(self::PRIVATE_DISK)->delete($path);
        }
    }

    private function safeExtension(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?? '');
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'pdf'], true)) {
            return $extension;
        }

        return match ($file->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'application/pdf' => 'pdf',
            default => 'bin',
        };
    }
}