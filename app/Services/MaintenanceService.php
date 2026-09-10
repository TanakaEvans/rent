<?php

namespace App\Services;

use App\Models\Contractor;
use App\Models\MaintenanceRequest;
use App\Models\Property;
use App\Models\User;
use App\Notifications\EmergencyMaintenanceNotification;
use App\Notifications\MaintenanceAssignedNotification;
use App\Notifications\MaintenanceClosedNotification;
use App\Notifications\MaintenanceSlaBreachedNotification;
use App\Notifications\MaintenanceStartedNotification;
use App\Notifications\MaintenanceTenantConfirmedNotification;
use App\Notifications\MaintenanceWorkCompletedNotification;
use App\Notifications\NewMaintenanceRequestNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Maintenance report, triage AND assignment (Module 10 + 11, Wave 5).
 *
 * Tenants report faults against a property they actively lease; the request
 * gets a unique MR number, a category/priority and a first-response SLA clock
 * driven by the maintenance.* configuration group. Emergency requests page
 * the owner AND staff immediately; the daily maintenance:escalate sweep moves
 * any reported request past its SLA into the staff escalation queue exactly
 * once (escalateDue is idempotent per request). The owner can then commit a
 * verified contractor to the job with an approved quote (reported -> assigned,
 * AC-01/AC-03), which delivers the job brief to the contractor.
 */
class MaintenanceService
{
    private const PRIORITIES = ['low', 'medium', 'high', 'emergency'];

    public function __construct(private readonly ConfigurationService $config)
    {
    }

    /**
     * Priority => first-response SLA window in hours (from configuration).
     */
    public function slaHoursFor(string $priority): int
    {
        return (int) $this->config->get("maintenance.sla.{$priority}_hours", 24);
    }

    /**
     * The tenant's leased (active) properties they may report against.
     */
    public function reportableProperties(User $tenant): Collection
    {
        return Property::query()
            ->whereHas('leases', function ($q) use ($tenant) {
                $q->where('tenant_id', $tenant->id)->where('status', 'active');
            })
            ->orderBy('title')
            ->get(['id', 'title', 'suburb', 'city']);
    }

    /**
     * File a maintenance request against a property the tenant actively
     * leases. Anything else is a 404 — tenants never see other people's homes.
     */
    public function create(User $tenant, array $data): MaintenanceRequest
    {
        $categories = (array) $this->config->get('maintenance.categories', ['plumbing', 'electrical', 'appliance', 'structural', 'pest', 'safety', 'other']);

        $category = $data['category'] ?? '';
        if (! in_array($category, $categories, true)) {
            throw ValidationException::withMessages(['category' => 'Please choose a valid fault category.']);
        }

        $priority = $data['priority'] ?? 'medium';
        if (! in_array($priority, self::PRIORITIES, true)) {
            throw ValidationException::withMessages(['priority' => 'Please choose a valid priority.']);
        }

        $property = Property::find((int) ($data['property_id'] ?? 0));
        if (! $property) {
            throw new NotFoundHttpException('Property not found.');
        }

        $active = $property->leases()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->exists();
        if (! $active) {
            throw new NotFoundHttpException('Property not found.');
        }

        if (trim((string) ($data['title'] ?? '')) === '') {
            throw ValidationException::withMessages(['title' => 'Give the issue a short title.']);
        }

        if (trim((string) ($data['description'] ?? '')) === '') {
            throw ValidationException::withMessages(['description' => 'Describe the issue so the owner can triage it.']);
        }

        $sequence = (int) MaintenanceRequest::query()->max('id') + 1;
        $padding = (int) $this->config->get('maintenance.request_no.padding', 5);

        $request = MaintenanceRequest::create([
            'request_no' => 'MR-'.now()->year.'-'.str_pad((string) $sequence, $padding, '0', STR_PAD_LEFT),
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'category' => $category,
            'priority' => $priority,
            'title' => trim($data['title']),
            'description' => trim($data['description']),
            'status' => 'reported',
            'sla_due_at' => now()->addHours($this->slaHoursFor($priority)),
        ]);

        $request->actions()->create(['actor_id' => $tenant->id, 'action' => 'reported']);

        $owner = User::find($property->owner_id);
        if ($owner) {
            $owner->notify(new NewMaintenanceRequestNotification($request));
        }

        if ($priority === 'emergency') {
            foreach (self::administrators() as $admin) {
                $admin->notify(new EmergencyMaintenanceNotification($request));
            }
        }

        return $request->fresh(['property', 'tenant']);
    }

    /**
     * The tenant's own requests, newest first.
     */
    public function listForTenant(User $tenant)
    {
        return MaintenanceRequest::query()
            ->with(['property', 'tenant'])
            ->where('tenant_id', $tenant->id)
            ->latest()
            ->paginate(12)
            ->withQueryString();
    }

    /**
     * The owner's maintenance triage desk for their properties.
     */
    public function listForOwner(User $owner)
    {
        return MaintenanceRequest::query()
            ->with(['property', 'tenant', 'contractor', 'rating'])
            ->whereHas('property', fn ($q) => $q->where('owner_id', $owner->id))
            ->latest()
            ->paginate(12)
            ->withQueryString();
    }

    /**
     * Commit a verified contractor to a reported request with the agreed
     * quote (Module 11 FR-03, Module 10 FR-05). Moves reported -> assigned,
     * records the quote + contractor on the request and the timeline, clears
     * any staff escalation flag, and delivers the job brief to the contractor
     * (Module 10 AC-02).
     */
    public function assignToContractor(User $owner, MaintenanceRequest $request, array $data): MaintenanceRequest
    {
        $property = Property::find($request->property_id);
        if (! $property || (int) $property->owner_id !== (int) $owner->id) {
            throw new NotFoundHttpException('Maintenance request not found.');
        }

        if ($request->status !== 'reported') {
            throw ValidationException::withMessages(['request' => 'This request is already being handled.']);
        }

        $quote = $data['approved_quote'] ?? '';
        if (! is_numeric($quote) || (float) $quote < 0 || (float) $quote > 9999999999) {
            throw ValidationException::withMessages(['approved_quote' => 'Quote the agreed cost first.']);
        }
        $quote = number_format((float) $quote, 2, '.', '');

        $contractor = Contractor::find((int) ($data['contractor_id'] ?? 0));
        if (! $contractor) {
            throw ValidationException::withMessages(['contractor_id' => 'Choose a contractor from the registry.']);
        }
        if ($contractor->status !== 'verified') {
            throw ValidationException::withMessages(['contractor_id' => 'Only verified contractors can be assigned (AC-01).']);
        }

        $request->update([
            'status' => 'assigned',
            'approved_quote' => $quote,
            'assigned_contractor_id' => $contractor->id,
            'escalated_at' => null,
        ]);

        $request->actions()->create([
            'actor_id' => $owner->id,
            'action' => 'assigned',
            'notes' => '$'.$quote.' — '.$contractor->business_name,
        ]);

        if ($contractor->user_id !== null) {
            $recipient = User::find($contractor->user_id);
            if ($recipient) {
                $recipient->notify(new MaintenanceAssignedNotification($request->fresh(['property', 'tenant', 'contractor'])));
            }
        }

        return $request->fresh(['property', 'tenant', 'contractor']);
    }

    /**
     * The contractor profile linked to a contractor login (or null).
     */
    private function contractorProfile(User $contractorUser): ?Contractor
    {
        return Contractor::query()->where('user_id', $contractorUser->id)->first();
    }

    /**
     * The assigned contractor begins work: assigned -> in_progress. Only the
     * assigned contractor can start the job (404 otherwise); the owner is
     * notified once work starts.
     */
    public function start(User $contractorUser, MaintenanceRequest $request): MaintenanceRequest
    {
        $profile = $this->contractorProfile($contractorUser);
        if (! $profile || (int) $request->assigned_contractor_id !== (int) $profile->id) {
            throw new NotFoundHttpException('Job not found.');
        }

        if (! $request->canTransitionTo('in_progress')) {
            throw ValidationException::withMessages(['request' => 'This job cannot be started from its current state.']);
        }

        $request->update(['status' => 'in_progress']);
        $request->actions()->create(['actor_id' => $contractorUser->id, 'action' => 'started']);

        $property = Property::find($request->property_id);
        $owner = $property ? User::find($property->owner_id) : null;
        if ($owner) {
            $owner->notify(new MaintenanceStartedNotification($request->fresh(['property', 'contractor'])));
        }

        return $request->fresh(['property', 'tenant', 'contractor']);
    }

    /**
     * The contractor reports the work done with a completion note:
     * in_progress -> completed. Pages both the owner (awaiting confirmation)
     * and the tenant (who must confirm before the owner can close).
     */
    public function complete(User $contractorUser, MaintenanceRequest $request, array $data = []): MaintenanceRequest
    {
        $profile = $this->contractorProfile($contractorUser);
        if (! $profile || (int) $request->assigned_contractor_id !== (int) $profile->id) {
            throw new NotFoundHttpException('Job not found.');
        }

        if (! $request->canTransitionTo('completed')) {
            throw ValidationException::withMessages(['request' => 'Only work in progress can be marked complete.']);
        }

        $note = trim((string) ($data['notes'] ?? ''));
        if ($note === '') {
            throw ValidationException::withMessages(['notes' => 'Summarise what was fixed.']);
        }

        $request->update(['status' => 'completed']);
        $request->actions()->create(['actor_id' => $contractorUser->id, 'action' => 'completed', 'notes' => $note]);

        $property = Property::find($request->property_id);
        $owner = $property ? User::find($property->owner_id) : null;
        if ($owner) {
            $owner->notify(new MaintenanceWorkCompletedNotification($request->fresh(['property', 'tenant', 'contractor'])));
        }

        $tenant = User::find($request->tenant_id);
        if ($tenant) {
            $tenant->notify(new MaintenanceWorkCompletedNotification($request->fresh(['property', 'tenant', 'contractor'])));
        }

        return $request->fresh(['property', 'tenant', 'contractor']);
    }

    /**
     * The tenant confirms the fix (FR-06). No status change — this is the gate
     * the owner needs before close; recorded once (idempotency guard).
     */
    public function confirm(User $tenant, MaintenanceRequest $request): MaintenanceRequest
    {
        if ((int) $request->tenant_id !== (int) $tenant->id) {
            throw new NotFoundHttpException('Maintenance request not found.');
        }

        if ($request->status !== 'completed') {
            throw ValidationException::withMessages(['request' => 'You can only confirm work that has been completed.']);
        }

        if ($request->tenant_confirmed_at !== null) {
            throw ValidationException::withMessages(['request' => 'You have already confirmed this fix.']);
        }

        $request->update(['tenant_confirmed_at' => now()]);
        $request->actions()->create(['actor_id' => $tenant->id, 'action' => 'tenant_confirmed']);

        $property = Property::find($request->property_id);
        $owner = $property ? User::find($property->owner_id) : null;
        if ($owner) {
            $owner->notify(new MaintenanceTenantConfirmedNotification($request->fresh(['property', 'tenant', 'contractor'])));
        }

        return $request->fresh(['property', 'tenant', 'contractor']);
    }

    /**
     * The owner closes the request after inspection: completed -> closed,
     * stamps `resolved_at`. Skips unless the tenant has confirmed the fix —
     * tenant confirmation gates close. Notifies the tenant and the contractor.
     */
    public function close(User $owner, MaintenanceRequest $request, array $data = []): MaintenanceRequest
    {
        $property = Property::find($request->property_id);
        if (! $property || (int) $property->owner_id !== (int) $owner->id) {
            throw new NotFoundHttpException('Maintenance request not found.');
        }

        if (! $request->canTransitionTo('closed')) {
            throw ValidationException::withMessages(['request' => 'Only completed requests can be closed.']);
        }

        if ($request->tenant_confirmed_at === null) {
            throw ValidationException::withMessages(['request' => 'Wait for the tenant to confirm the fix before closing.']);
        }

        $note = trim((string) ($data['notes'] ?? ''));
        $request->update(['status' => 'closed', 'resolved_at' => now()]);
        $request->actions()->create([
            'actor_id' => $owner->id,
            'action' => 'closed',
            'notes' => $note !== '' ? $note : null,
        ]);

        $fresh = $request->fresh(['property', 'tenant', 'contractor']);

        $tenant = User::find($request->tenant_id);
        if ($tenant) {
            $tenant->notify(new MaintenanceClosedNotification($fresh));
        }

        if ($request->assigned_contractor_id !== null) {
            $profile = Contractor::find($request->assigned_contractor_id);
            $contractorUser = $profile && $profile->user_id !== null ? User::find($profile->user_id) : null;
            if ($contractorUser) {
                $contractorUser->notify(new MaintenanceClosedNotification($fresh));
            }
        }

        return $fresh;
    }

    /**
     * Jobs handed to this contractor (their profile is linked behind a login).
     */
    public function listForContractor(User $contractorUser)
    {
        $profile = Contractor::query()->where('user_id', $contractorUser->id)->first();

        return MaintenanceRequest::query()
            ->with(['property', 'property.owner', 'tenant', 'contractor'])
            ->when($profile, fn ($q) => $q->where('assigned_contractor_id', $profile->id))
            ->when(! $profile, fn ($q) => $q->whereRaw('1 = 0'))
            ->latest()
            ->paginate(12)
            ->withQueryString();
    }

    /**
     * The staff escalation queue: reported requests whose first-response SLA
     * has been breached and are awaiting a staff owner, newest first.
     */
    public function listEscalationsForAdmin()
    {
        return MaintenanceRequest::query()
            ->with(['property', 'tenant'])
            ->where('status', 'reported')
            ->whereNotNull('escalated_at')
            ->latest('escalated_at')
            ->paginate(12)
            ->withQueryString();
    }

    /**
     * Take ownership of an escalated request. Records the staff member on the
     * timeline and drops the request out of the escalation queue; the case
     * continues as a normal reported request from the new assignee.
     */
    public function acknowledge(MaintenanceRequest $request, User $admin): MaintenanceRequest
    {
        if ($request->status !== 'reported' || $request->escalated_at === null) {
            throw ValidationException::withMessages([
                'request' => 'This request is not waiting on staff attention.',
            ]);
        }

        $request->update(['escalated_at' => null]);
        $request->actions()->create(['actor_id' => $admin->id, 'action' => 'acknowledged']);

        return $request->fresh(['property', 'tenant']);
    }

    /**
     * Sweep: escalate every reported request past its SLA clock, exactly once
     * (escalated_at guards re-alerting). Returns how many were escalated.
     */
    public function escalateDue(): int
    {
        if (! $this->config->get('maintenance.escalation_enabled', true)) {
            return 0;
        }

        $due = MaintenanceRequest::query()
            ->where('status', 'reported')
            ->whereNull('escalated_at')
            ->where('sla_due_at', '<', Carbon::now())
            ->get();

        $escalated = 0;
        foreach ($due as $request) {
            $request->update(['escalated_at' => now()]);
            $request->actions()->create(['action' => 'escalated']);

            foreach (self::administrators() as $admin) {
                $admin->notify(new MaintenanceSlaBreachedNotification($request));
            }

            $escalated++;
        }

        return $escalated;
    }

    /**
     * All platform staff (Admin/Superuser roles) who police the escalation queue.
     */
    public static function administrators(): Collection
    {
        return User::query()
            ->where('status', 'active')
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['Admin', 'Superuser']))
            ->get();
    }
}