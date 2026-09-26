<?php

namespace App\Services;

use App\Models\Lease;
use App\Models\Property;
use App\Models\RentalApplication;
use App\Models\User;
use App\Notifications\LeaseCreatedNotification;
use App\Notifications\LeaseSentForSignatureNotification;
use App\Notifications\LeaseSignedNotification;
use App\Notifications\LeaseTerminatedNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LeaseService
{
    /**
     * Rent due-date wording per listing payment term.
     */
    private const TERM_DESCRIPTIONS = [
        'monthly' => 'Rent is due on the 1st of each month.',
        'quarterly' => 'Rent is due in advance at the start of each quarter.',
        'yearly' => 'Rent is due in advance at the start of each lease year.',
    ];

    public function __construct(
        private readonly PropertyService $properties,
        private readonly SubscriptionService $subscriptions,
    ) {
    }

    /**
     * The lease payment terms carried over from the listing:
     * `{"frequency": "monthly|quarterly|yearly", "due_day": 1, "description": "..."}`.
     *
     * @return array{frequency: string, due_day: int, description: string}
     */
    private function paymentTermsFor(Property $property): array
    {
        $frequency = isset(self::TERM_DESCRIPTIONS[$property->payment_terms]) ? $property->payment_terms : 'monthly';

        return [
            'frequency' => $frequency,
            'due_day' => 1,
            'description' => self::TERM_DESCRIPTIONS[$frequency],
        ];
    }

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
     * property, rent, deposit, currency and payment terms from the listing,
     * over the owner's chosen dates (default today → +1 year); sets the
     * property to reserved, auto-rejects remaining active applicants and
     * notifies the tenant. A property takes a new lease once it has no lease
     * in progress or running (Lease::OPEN) and is Available.
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

        $openLease = Lease::where('property_id', $property->id)->whereIn('status', Lease::OPEN)->first();
        if ($openLease) {
            throw ValidationException::withMessages([
                'application' => ['This property already has lease '.$openLease->lease_no.' ('.Lease::STATUSES[$openLease->status].'). End or complete it before creating another lease.'],
            ]);
        }

        if ($property->status !== 'available') {
            throw ValidationException::withMessages([
                'application' => ['"'.$property->title.'" is currently '.Property::STATUSES[$property->status].'. Set it to Available before generating a lease.'],
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
                'currency' => $property->currency ?: 'USD',
                'payment_terms' => $this->paymentTermsFor($property),
                'status' => 'draft',
                'clause_version' => 1,
            ]);

            $lease->recordHistory('lease_created', $owner, [
                'status' => 'draft',
                'from' => 'approved_application#'.$application->id,
            ]);

            $this->properties->changeStatus($property->id, 'reserved', $owner);

            $this->rejectRemainingApplicants($owner, $property, $application);

            $lease->tenant->notify(new LeaseCreatedNotification($lease));

            return $lease->fresh(['property', 'tenant:id,name,email', 'application', 'history.performer:id,name']);
        });
    }

    /**
     * Auto-reject still-active applicants on the property once a lease exists
     * (the handover that was deferred from the application slice).
     */
    private function rejectRemainingApplicants(User $owner, Property $property, RentalApplication $excluded): void
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
     * Create a renewal draft that continues an active lease (FR-05).
     * Copies the terms with updated dates and links the new lease to the
     * original via `renewed_from_id`; the original only becomes `renewed`
     * once the renewal has been signed (activated).
     */
    public function renew(User $owner, int $id, array $data): Lease
    {
        $lease = $this->findOwnedByOwner($owner, $id);

        if ($lease->status !== 'active') {
            throw ValidationException::withMessages([
                'status' => ['Only an active lease can be renewed.'],
            ]);
        }

        if (Lease::where('renewed_from_id', $lease->id)
            ->whereIn('status', ['draft', 'sent', 'signed'])
            ->exists()) {
            throw ValidationException::withMessages([
                'status' => ['This lease already has a renewal in progress.'],
            ]);
        }

        $startDate = isset($data['start_date'])
            ? Carbon::parse($data['start_date'])->startOfDay()
            : ($lease->end_date?->copy()->startOfDay() ?: now()->startOfDay());
        $endDate = isset($data['end_date'])
            ? Carbon::parse($data['end_date'])->startOfDay()
            : $startDate->copy()->addYear();

        if ($endDate->lte($startDate)) {
            throw ValidationException::withMessages([
                'end_date' => ['The lease end date must be after the start date.'],
            ]);
        }

        return DB::transaction(function () use ($owner, $lease, $startDate, $endDate) {
            $renewal = Lease::create([
                'property_id' => $lease->property_id,
                'tenant_id' => $lease->tenant_id,
                'application_id' => null,
                'renewed_from_id' => $lease->id,
                'lease_no' => $this->nextLeaseNo(),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'rent_amount' => $lease->rent_amount,
                'deposit_amount' => $lease->deposit_amount,
                'currency' => $lease->currency,
                'payment_terms' => $lease->payment_terms,
                'status' => 'draft',
                'clause_version' => $lease->clause_version,
            ]);

            $renewal->recordHistory('lease_renewal', $owner, [
                'status' => 'draft',
                'renewed_from' => $lease->lease_no,
            ]);

            return $renewal->fresh(['property', 'tenant:id,name,email', 'renewedFrom:id,lease_no,status']);
        });
    }

    /**
     * End an active lease early (owner). Records the end date and reason,
     * closes any renewal still being drafted or signed, and frees the
     * property: back to Available when the owner's plan has room for another
     * published listing, otherwise Unavailable. Both parties are notified.
     * The end date may not be in the future or before the lease started.
     */
    public function terminate(User $owner, int $id, array $data): Lease
    {
        $lease = $this->findOwnedByOwner($owner, $id);

        if ($lease->status !== 'active') {
            throw ValidationException::withMessages([
                'status' => ['Only an active lease can be ended.'],
            ]);
        }

        $endedOn = Carbon::parse($data['terminated_on'])->startOfDay();

        if ($lease->start_date && $endedOn->lt($lease->start_date)) {
            throw ValidationException::withMessages([
                'terminated_on' => ['The end date cannot be before the lease started ('.$lease->start_date->format('d M Y').').'],
            ]);
        }

        DB::transaction(function () use ($lease, $owner, $endedOn, $data) {
            $lease->update([
                'status' => 'terminated',
                'terminated_on' => $endedOn,
                'termination_reason' => $data['reason'],
            ]);
            $lease->recordHistory('lease_terminated', $owner, [
                'status' => 'terminated',
                'terminated_on' => $endedOn->toDateString(),
                'reason' => $data['reason'],
            ]);

            Lease::where('renewed_from_id', $lease->id)
                ->whereIn('status', ['draft', 'sent', 'signed'])
                ->get()
                ->each(function (Lease $renewal) use ($owner, $lease) {
                    $renewal->update(['status' => 'terminated', 'terminated_on' => $lease->terminated_on, 'termination_reason' => 'The lease it renewed was ended.']);
                    $renewal->recordHistory('lease_terminated', $owner, ['status' => 'terminated', 'reason' => 'renewed lease ended']);
                });

            app(RentService::class)->cancelInvoicesAfter($lease, $endedOn);

            if ($lease->property->status === 'occupied') {
                $target = $this->subscriptions->hasQuota($owner, 1) ? 'available' : 'unavailable';
                $this->properties->changeStatus($lease->property_id, $target, $owner);
            }
        });

        $lease = $lease->fresh(['property', 'tenant']);
        $lease->tenant->notify(new LeaseTerminatedNotification($lease));
        $owner->notify(new LeaseTerminatedNotification($lease));

        return $lease;
    }

    /**
     * Leases against an owner's properties, newest first.
     */
    public function listForOwner(User $owner)
    {
        return Lease::with([
            'property:id,title,price,currency,payment_terms,property_type,suburb,city,cover_image,status',
            'tenant:id,name,email',
            'application:id,status',
            'signatures.user:id,name',
            'renewedFrom:id,lease_no,status',
            'renewals:id,lease_no,status',
            'document:id,lease_id,name,type,version',
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
            'property:id,owner_id,title,price,currency,payment_terms,property_type,suburb,city,cover_image,status,verified',
            'property.owner:id,name,email',
            'signatures.user:id,name',
            'renewedFrom:id,lease_no,status',
            'renewals:id,lease_no,status',
            'document:id,lease_id,name,type,version',
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

                if ($lease->property->status !== 'occupied') {
                    $this->properties->changeStatus($lease->property_id, 'occupied', $lease->property->owner);
                }

                if ($lease->renewed_from_id) {
                    $source = $lease->renewedFrom()->first();
                    if ($source && $source->status === 'active') {
                        $source->update(['status' => 'renewed']);
                        $source->recordHistory('lease_renewed', $user, [
                            'status' => 'renewed',
                            'superseded_by' => $lease->lease_no,
                        ]);
                    }
                }

                app(DocumentService::class)->storeLeaseAgreement($lease);

                app(RentService::class)->generateFor($lease);

                $counterpart = $user->id === $lease->property->owner_id ? $lease->tenant : $lease->property->owner;
                $counterpart->notify(new LeaseSignedNotification($lease->fresh(['property'])));
            }

            return $lease->fresh(['property', 'tenant:id,name,email', 'signatures.user:id,name']);
        });
    }
}