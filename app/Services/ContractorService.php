<?php

namespace App\Services;

use App\Models\Contractor;
use App\Models\ContractorRating;
use App\Models\ContractorTrade;
use App\Models\MaintenanceRequest;
use App\Models\Property;
use App\Models\User;
use App\Notifications\ContractorRatedNotification;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Contractor registry + ratings (Module 11, Wave 5 slices 2-4).
 *
 * Admins register tradespeople and move their verification status along the
 * FR-02 machine (unverified -> vetting -> verified, verified <-> suspended).
 * Owners only ever see and hire `verified` contractors (AC-01); a suspended
 * contractor receives no new assignments (AC-03). Trades are held on the
 * contractor_trades ledger with an optional rate. Once a job has closed, the
 * owner rates the contractor (1-5 + note); the aggregate rating_avg and
 * jobs_completed are recomputed server-side (FR-04, AC-02, NFR-01).
 */
class ContractorService
{
    /**
     * The full registry for the admin centre, newest first.
     */
    public function listForAdmin()
    {
        return Contractor::query()
            ->with(['user', 'trades'])
            ->withCount('assignments')
            ->latest()
            ->paginate(12)
            ->withQueryString();
    }

    /**
     * Verified contractors a given owner may assign to (AC-01). Rated best
     * first so repeat hiring surfaces the best performers.
     */
    public function verifiedForAssign(User $owner): Collection
    {
        return Contractor::query()
            ->where('status', 'verified')
            ->with('trades')
            ->orderByDesc('rating_avg')
            ->orderByDesc('jobs_completed')
            ->get(['id', 'business_name', 'contact', 'service_area', 'status', 'rating_avg', 'jobs_completed']);
    }

    /**
     * Register a tradesperson. New profiles always land in `vetting` — the
     * registry only opens a profile to owners once staff verifies it (FR-02).
     */
    public function register(array $data): Contractor
    {
        $businessName = trim((string) ($data['business_name'] ?? ''));
        if ($businessName === '') {
            throw ValidationException::withMessages(['business_name' => 'Enter the business name.']);
        }

        $contact = trim((string) ($data['contact'] ?? ''));
        if ($contact === '') {
            throw ValidationException::withMessages(['contact' => 'Enter a contact number or email.']);
        }

        $areas = (array) ($data['service_area'] ?? []);
        $areas = array_values(array_filter(array_map('trim', $areas)));
        if (count($areas) === 0) {
            throw ValidationException::withMessages(['service_area' => 'Add at least one service area.']);
        }

        $userId = isset($data['user_id']) && $data['user_id'] !== '' && $data['user_id'] !== null
            ? (int) $data['user_id']
            : null;
        if ($userId !== null && ! User::query()->whereKey($userId)->exists()) {
            throw ValidationException::withMessages(['user_id' => 'The linked user does not exist.']);
        }

        $trades = $this->normaliseTrades($data['trades'] ?? []);

        $contractor = Contractor::create([
            'user_id' => $userId,
            'business_name' => $businessName,
            'contact' => $contact,
            'service_area' => $areas,
            'status' => 'vetting',
        ]);

        foreach ($trades as $trade) {
            ContractorTrade::create([
                'contractor_id' => $contractor->id,
                'trade' => $trade['trade'],
                'rate' => $trade['rate'],
            ]);
        }

        return $contractor->fresh(['trades']);
    }

    /**
     * Move a contractor along the FR-02 state machine. Recording `verified`
     * stamps verified_at; leaving it clears the badge context.
     */
    public function setStatus(Contractor $contractor, string $status): Contractor
    {
        if (! in_array($status, array_keys(Contractor::STATUSES), true)) {
            throw ValidationException::withMessages(['status' => 'Choose a valid registry status.']);
        }

        if (! $contractor->canTransitionTo($status)) {
            throw ValidationException::withMessages([
                'status' => "A {$contractor->status} contractor cannot be moved straight to {$status}.",
            ]);
        }

        $cleanup = in_array($status, ['unverified', 'vetting', 'suspended'], true);
        $contractor->update([
            'status' => $status,
            'verified_at' => $status === 'verified' ? now() : ($cleanup ? null : $contractor->verified_at),
        ]);

        return $contractor->fresh();
    }

    /**
     * The owner scores the contractor's closed job (FR-04 / AC-02 / NFR-01).
     *
     * Only the property owner may rate; the request must have fully closed
     * (ratings reflect completed jobs only), a contractor must have been
     * assigned, and each job is rated exactly once (unique request_id
     * enforces it in the schema). The contractor's rating_avg is recomputed
     * as a jobs-weighted average and jobs_completed incremented — all
     * server-side, never client arithmetic.
     */
    public function rate(User $owner, MaintenanceRequest $request, array $data): MaintenanceRequest
    {
        $property = Property::find($request->property_id);
        if (! $property || (int) $property->owner_id !== (int) $owner->id) {
            throw new NotFoundHttpException('Maintenance request not found.');
        }

        if ($request->status !== 'closed') {
            throw ValidationException::withMessages(['request' => 'Only closed jobs can be rated (AC-02).']);
        }

        if ($request->assigned_contractor_id === null) {
            throw ValidationException::withMessages(['request' => 'No contractor was assigned to this request.']);
        }

        if (ContractorRating::query()->where('request_id', $request->id)->exists()) {
            throw ValidationException::withMessages(['request' => 'This job has already been rated.']);
        }

        $score = $data['rating'] ?? '';
        if ((string) (int) $score !== (string) $score || (int) $score < 1 || (int) $score > 5) {
            throw ValidationException::withMessages(['rating' => 'Choose a rating between 1 and 5.']);
        }
        $score = (int) $score;

        $note = trim((string) ($data['note'] ?? ''));
        $note = mb_substr($note, 0, 500);

        $contractor = Contractor::find($request->assigned_contractor_id);
        if (! $contractor) {
            throw ValidationException::withMessages(['request' => 'The assigned contractor no longer exists.']);
        }

        ContractorRating::create([
            'request_id' => $request->id,
            'contractor_id' => $contractor->id,
            'owner_id' => $owner->id,
            'rating' => $score,
            'note' => $note !== '' ? $note : null,
        ]);

        $previousJobs = max(0, (int) $contractor->jobs_completed);
        $previousAvg = max(0.0, (float) $contractor->rating_avg);
        $jobs = $previousJobs + 1;
        $average = ($previousAvg * $previousJobs + $score) / $jobs;
        $contractor->update([
            'jobs_completed' => $jobs,
            'rating_avg' => number_format(round($average, 2), 2, '.', ''),
        ]);

        $request->actions()->create([
            'actor_id' => $owner->id,
            'action' => 'rated',
            'notes' => $score.'/5'.($note !== '' ? ' — '.mb_substr($note, 0, 480) : ''),
        ]);

        if ($contractor->user_id !== null) {
            $recipient = User::find($contractor->user_id);
            if ($recipient) {
                $recipient->notify(new ContractorRatedNotification($request->fresh(['property']), $score, $note !== '' ? $note : null));
            }
        }

        return $request->fresh(['property', 'tenant', 'contractor', 'rating']);
    }

    /**
     * Validate the trade rows (trade required, rate optional but money-shaped).
     */
    private function normaliseTrades(array $raw): array
    {
        $trades = [];
        foreach ($raw as $row) {
            $trade = trim((string) ($row['trade'] ?? ''));
            if ($trade === '') {
                throw ValidationException::withMessages(['trades' => 'Every trade needs a name.']);
            }
            if (mb_strlen($trade) > 60) {
                throw ValidationException::withMessages(['trades' => 'Trade names are limited to 60 characters.']);
            }

            $rate = null;
            if (isset($row['rate']) && $row['rate'] !== '' && $row['rate'] !== null) {
                if (is_numeric($row['rate']) && (float) $row['rate'] >= 0 && (float) $row['rate'] <= 99999999.99) {
                    $rate = number_format((float) $row['rate'], 2, '.', '');
                } else {
                    throw ValidationException::withMessages(['trades' => 'Each rate must be a valid amount.']);
                }
            }

            $trades[] = ['trade' => $trade, 'rate' => $rate];
        }

        return $trades;
    }
}