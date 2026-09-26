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
use Illuminate\Support\Facades\DB;
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
            ->with(['user:id,name,email', 'trades'])
            ->withCount('assignments')
            ->latest()
            ->paginate(12)
            ->withQueryString();
    }

    /**
     * Registry headcounts across every profile (not just the current page).
     *
     * @return array{total: int, verified: int, under_review: int, suspended: int}
     */
    public function registryStats(): array
    {
        $counts = Contractor::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'total' => (int) $counts->sum(),
            'verified' => (int) ($counts['verified'] ?? 0),
            'under_review' => (int) ($counts['unverified'] ?? 0) + (int) ($counts['vetting'] ?? 0),
            'suspended' => (int) ($counts['suspended'] ?? 0),
        ];
    }

    /**
     * Verified contractors a given owner may assign to (AC-01). Rated best
     * first so repeat hiring surfaces the best performers.
     */
    public function verifiedForAssign(User $owner): Collection
    {
        return Contractor::query()
            ->where('status', 'verified')
            ->with('trades:id,contractor_id,trade,rate')
            ->orderByDesc('rating_avg')
            ->orderByDesc('jobs_completed')
            ->get(['id', 'business_name', 'contact', 'service_area', 'status', 'rating_avg', 'jobs_completed']);
    }

    /**
     * Register a tradesperson. New profiles always land in `vetting` — the
     * registry only opens a profile to owners once staff verifies it (FR-02).
     * An optional login (existing account, looked up by email) is linked and
     * granted the Contractor role so the "My Jobs" desk opens for them.
     */
    public function register(array $data, ?User $actor = null): Contractor
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

        $login = null;
        $email = trim((string) ($data['user_email'] ?? ''));
        if ($email !== '') {
            $login = $this->linkableUser($email, 'user_email');
        } elseif (isset($data['user_id']) && $data['user_id'] !== '' && $data['user_id'] !== null) {
            $login = User::find((int) $data['user_id']);
            if (! $login) {
                throw ValidationException::withMessages(['user_id' => 'The linked user does not exist.']);
            }
            $this->assertNotLinkedElsewhere($login, 'user_id');
        }

        $trades = $this->normaliseTrades($data['trades'] ?? []);

        return DB::transaction(function () use ($login, $businessName, $contact, $areas, $trades, $actor) {
            $contractor = Contractor::create([
                'user_id' => $login?->id,
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

            $login?->assignRole('Contractor', $actor?->id);

            return $contractor->fresh(['trades', 'user']);
        });
    }

    /**
     * Link an existing platform account (by email) to a contractor profile
     * that has no login yet, and grant it the Contractor role so the account
     * can open the "My Jobs" desk.
     */
    public function linkLogin(Contractor $contractor, string $email, ?User $actor = null): Contractor
    {
        if ($contractor->user_id !== null) {
            throw ValidationException::withMessages(['user_email' => 'This contractor is already linked to a login.']);
        }

        $login = $this->linkableUser($email, 'user_email');

        return DB::transaction(function () use ($contractor, $login, $actor) {
            $contractor->update(['user_id' => $login->id]);
            $login->assignRole('Contractor', $actor?->id);

            return $contractor->fresh(['trades', 'user']);
        });
    }

    /**
     * Resolve the account behind an email for linking. The account must
     * exist (contractors sign up first) and may back only one profile.
     */
    private function linkableUser(string $email, string $field): User
    {
        $email = mb_strtolower(trim($email));
        $login = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if (! $login) {
            throw ValidationException::withMessages([$field => 'No account uses that email. Ask the contractor to sign up first.']);
        }

        $this->assertNotLinkedElsewhere($login, $field);

        return $login;
    }

    private function assertNotLinkedElsewhere(User $login, string $field): void
    {
        if (Contractor::query()->where('user_id', $login->id)->exists()) {
            throw ValidationException::withMessages([$field => 'That account is already linked to another contractor profile.']);
        }
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