<?php

namespace App\Services;

use App\Models\Lease;
use App\Models\RentalApplication;
use App\Models\User;
use App\Notifications\LeaseCreatedNotification;
use App\Notifications\LeaseSentForSignatureNotification;
use App\Notifications\LeaseSignedNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LeaseService
{
    /**
     * Default terms applied to every generated lease until the
     * payment-terms specification lands in Wave 4.
     */
    private const DEFAULT_PAYMENT_TERMS = [
        'frequency' => 'monthly',
        'due_day' => 1,
        'description' => 'Rent is due on the 1st of each month.',
    ];

    /**
     * Fetch a lease attached to one of the owner's properties
     * (row-level isolation, 404 on foreign rows).
     */
    public function findOwnedByOwner(User $owner, int $id): Lease
    {
        $lease = Lease::with(['property', 'tenant:id,name,email', 'history.performer:id,name'])
            ->whereHas('property', fn ($query) => $query->where('owner_id', $owner->id))
            ->find($id);

        if (! $lease) {
            throw new NotFoundHttpException('Lease not found.');
        }

        return $lease;
    }

    /**
     * Generate a unique, human-friendly lease number.
     */
    private function nextLeaseNo(): string
    {
        $prefix = 'LSE-'.now()->year.'-';
        $offset = Lease::where('lease_no', 'like', $prefix.'%')->count();

        do {
            $candidate = $prefix.str_pad((string) ++$offset, 4, '0', STR_PAD_LEFT);
        } while (Lease::where('lease_no', $candidate)->exists());

        return $candidate;
    }

    /**
     * Generate a lease from an approved application. Prefills the parties,
     * property, rent and deposit from the application; sets the property to
     * reserved, auto-rejects remaining active applicants and notifies the tenant.
     */
    public function createFromApplication(User $owner, RentalApplication $application, array $data): Lease
    {
        if ($application->status !== 'approved') {
            throw ValidationException::withMessages([
                'status' => ['Only an approved application can be converted into a lease.'],
            ]);
        }

        $property = $application->property()->first();

        if ($property->owner_id !== $owner->id) {
            throw new NotFoundHttpException('Lease not found.');
        }

        if (Lease::where('application_id', $application->id)->exists()) {
            throw ValidationException::withMessages([
                'application' => ['A lease has already been generated from this application.'],
            ]);
        }

        if (Lease::where('property_id', $property->id)->exists()) {
            throw ValidationException::withMessages([
                'application' => ['This property already has a lease.'],
            ]);
        }

        $startDate = isset($data['start_date']) ? Carbon::parse($data['start_date'])->startOfDay() : now()->startOfDay();
        $endDate = isset($data['end_date']) ? Carbon::parse($data['end_date'])->startOfDay() : $startDate->copy()->addYear();

        if ($endDate->lte($startDate)) {
            throw ValidationException::withMessages([
                'end_date' => ['The lease end date must be after the start date.'],
            ]);
        }

        return DB::transaction(function () use ($owner, $application, $property, $startDate, $endDate) {
            $lease = Lease::create([
                'property_id' => $property->id,
                'tenant_id' => $application->applicant_id,
                'application_id' => $application->id,
                'lease_no' => $this->nextLeaseNo(),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'rent_amount' => $property->price,
                'deposit_amount' => $property->deposit ?? 0,
                'payment_terms' => self::DEFAULT_PAYMENT_TERMS,
                'status' => 'draft',
                'clause_version' => 1,
            ]);

            $lease->recordHistory('lease_created', $owner, [
                'status' => 'draft',
                'from' => 'approved_application#'.$application->id,
            ]);

            app(PropertyService::class)->changeStatus($property->id, 'reserved', $owner);

            $this->rejectRemainingApplicants($owner, $property, $application);

            $lease->tenant->notify(new LeaseCreatedNotification($lease));

            return $lease->fresh(['property', 'tenant:id,name,email', 'application', 'history.performer:id,name']);
        });
    }

    /**
     * Auto-reject still-active applicants on the property once a lease exists
     * (the handover that was deferred from the application slice).
     */
    private function rejectRemainingApplicants(User $owner, \App\Models\Property $property, RentalApplication $excluded): void
    {
        $others = RentalApplication::where('property_id', $property->id)
            ->where('id', '!=', $excluded->id)
            ->whereIn('status', ApplicationService::ACTIVE)
            ->get();

        foreach ($others as $other) {
            if ($other->canTransitionTo('rejected')) {
                app(ApplicationService::class)->reject(
                    $owner,
                    $other->id,
                    'A lease has been created for another applicant.'
                );
            }
        }
    }

    /**
     * Leases against an owner's properties, newest first.
     */
    public function listForOwner(User $owner)
    {
        return Lease::with([
            'property:id,title,price,property_type,suburb,city,cover_image,status',
            'tenant:id,name,email',
            'application:id,status',
            'signatures.user:id,name',
        ])->whereHas('property', fn ($query) => $query->where('owner_id', $owner->id))
            ->latest()
            ->get();
    }

    /**
     * Leases where the tenant is the lessee, newest first.
     */
    public function listForTenant(User $tenant)
    {
        return Lease::with([
            'property:id,title,price,property_type,suburb,city,cover_image,status,verified',
            'property.owner:id,name,email',
            'signatures.user:id,name',
        ])->where('tenant_id', $tenant->id)
            ->latest()
            ->get();
    }

    /**
     * Send a draft lease to the tenant for signing.
     */
    public function sendForSignature(User $owner, int $id): Lease
    {
        $lease = $this->findOwnedByOwner($owner, $id);

        if (! $lease->canTransitionTo('sent')) {
            throw ValidationException::withMessages([
                'status' => ['Only a draft lease can be sent for signature.'],
            ]);
        }

        DB::transaction(function () use ($lease, $owner) {
            $lease->update(['status' => 'sent']);
            $lease->recordHistory('lease_sent', $owner, ['status' => 'sent']);
        });

        $lease->tenant->notify(new LeaseSentForSignatureNotification($lease->fresh(['property'])));

        return $lease->fresh(['property', 'tenant:id,name,email', 'signatures.user:id,name']);
    }

    /**
     * Record a digital signature for one of the parties on a `sent` lease.
     * Once both parties have signed, the lease moves `sent → signed → active`
     * and the property moves `reserved → occupied`.
     */
    public function sign(User $user, int $id, ?string $payload): Lease
    {
        $lease = Lease::with(['property.owner', 'tenant'])->findOrFail($id);

        abort_unless(
            $lease->property->owner_id === $user->id || $lease->tenant_id === $user->id,
            404
        );

        if ($lease->status !== 'sent') {
            throw ValidationException::withMessages([
                'status' => ['This lease is not awaiting signatures.'],
            ]);
        }

        if ($lease->signatures()->where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'status' => ['You have already signed this lease.'],
            ]);
        }

        return DB::transaction(function () use ($lease, $user, $payload) {
            $lease->signatures()->create([
                'user_id' => $user->id,
                'signed_at' => now(),
                'signature_payload' => $payload ?: $user->name,
            ]);
            $lease->recordHistory('lease_signed', $user, ['party' => 'owner or tenant', 'user_id' => $user->id]);

            if ($lease->signatures()->count() >= 2) {
                $lease->update(['status' => 'signed']);
                $lease->recordHistory('lease_signed_final', $user, ['status' => 'signed']);

                $lease->update(['status' => 'active']);
                $lease->recordHistory('lease_activated', $user, ['status' => 'active']);

                app(PropertyService::class)->changeStatus($lease->property_id, 'occupied', $lease->property->owner);

                $counterpart = $user->id === $lease->property->owner_id ? $lease->tenant : $lease->property->owner;
                $counterpart->notify(new LeaseSignedNotification($lease->fresh(['property'])));
            }

            return $lease->fresh(['property', 'tenant:id,name,email', 'signatures.user:id,name']);
        });
    }
}