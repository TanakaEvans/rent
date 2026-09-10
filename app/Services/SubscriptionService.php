<?php

namespace App\Services;

use App\Models\Property;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubscriptionService
{
    public function __construct(private readonly ConfigurationService $config)
    {
    }

    /**
     * Return the owner's current subscription, lazily subscribing them to the
     * configured default plan on first use (FR-02; there is no public
     * registration yet, so the default plan is attached on demand instead of
     * at sign-up). Active/archived default plan is chosen per
     * `subscriptions.default_plan`.
     */
    public function ensureFor(User $owner): Subscription
    {
        $current = $owner->currentSubscription();
        if ($current) {
            return $current;
        }

        $defaultPlanName = $this->config->get('subscriptions.default_plan', 'Free');
        $default = SubscriptionPlan::where('name', $defaultPlanName)->first();

        if (! $default) {
            throw ValidationException::withMessages([
                'plan_id' => "Default plan [{$defaultPlanName}] does not exist. Configure `subscriptions.default_plan` or seed the plan catalogue.",
            ]);
        }

        return DB::transaction(function () use ($owner, $default) {
            $subscription = $owner->subscriptions()->create([
                'plan_id' => $default->id,
                'status' => 'active',
                'starts_at' => now(),
                'ends_at' => $this->cycleEnd(now(), $default->billing_cycle ?: 'monthly'),
                'cycle' => $default->billing_cycle ?: 'monthly',
                'details' => null,
            ]);
            $subscription->recordEvent('subscribed', ['plan' => $default->name]);

            return $subscription;
        });
    }

    /**
     * The listing quota the owner may currently publish: the plan limit while
     * active or in grace, the Free limit once suspended (null = unlimited).
     */
    public function effectiveLimit(User $owner): ?int
    {
        $subscription = $owner->currentSubscription();
        if (! $subscription) {
            return $this->freeLimit();
        }

        if (in_array($subscription->status, ['active', 'grace'], true)) {
            return match (true) {
                is_null($subscription->plan) => $this->freeLimit(),
                is_null($subscription->plan->listing_limit) => null,
                default => $subscription->plan->listing_limit,
            };
        }

        return $this->freeLimit();
    }

    /**
     * Number of live listings the owner is currently running.
     */
    public function publishedCount(User $owner): int
    {
        return Property::where('owner_id', $owner->id)
            ->where('status', 'available')
            ->count();
    }

    /**
     * Whether the owner may publish another listing (extra = listings about
     * to be added in the same request).
     */
    public function hasQuota(User $owner, int $extra = 0): bool
    {
        $limit = $this->effectiveLimit($owner);
        if ($limit === null) {
            return true;
        }

        return $this->publishedCount($owner) + $extra <= $limit;
    }

    /**
     * The configured upgrade-proration policy
     * (`subscriptions.proration.mode`: charge_difference / credit_new_invoice
     * / apply_at_renewal).
     */
    public function prorationMode(): string
    {
        return (string) $this->config->get('subscriptions.proration.mode', 'credit_new_invoice');
    }

    /**
     * Subscribe the owner to a plan. Upgrades lift the limit immediately and
     * are prorated per `subscriptions.proration.mode` (charge the price
     * difference, credit the unused portion of the current cycle, or defer
     * the change to the next boundary); downgrades are deferred to the end of
     * the current cycle (FR-05 / AC-03).
     */
    public function subscribe(User $owner, int $planId): Subscription
    {
        $plan = SubscriptionPlan::find($planId);
        if (! $plan || $plan->status !== 'active') {
            throw ValidationException::withMessages(['plan_id' => 'This plan is not available for subscription.']);
        }

        $current = $owner->currentSubscription();

        if (! $current || in_array($current->status, ['cancelled', 'suspended'], true)) {
            return $this->startFreshSubscription($owner, $plan);
        }

        $currentCents = $this->cents($current->plan?->price ?? 0);
        $newCents = $this->cents($plan->price);

        if ($newCents > $currentCents) {
            return $this->upgrade($current, $plan, $currentCents, $newCents);
        }

        if ($newCents < $currentCents) {
            return $this->downgrade($current, $plan, $newCents);
        }

        return $this->switchEqual($current, $plan);
    }

    /**
     * Move a brand-new cycle subscription (attached to no usable subscription,
     * or a suspended/cancelled one) onto the chosen plan.
     */
    private function startFreshSubscription(User $owner, SubscriptionPlan $plan): Subscription
    {
        $cycle = $plan->billing_cycle ?: 'monthly';

        return \Illuminate\Support\Facades\DB::transaction(function () use ($owner, $plan, $cycle) {
            $subscription = $owner->subscriptions()->create([
                'plan_id' => $plan->id,
                'status' => 'active',
                'starts_at' => now(),
                'ends_at' => $this->cycleEnd(now(), $cycle),
                'cycle' => $cycle,
                'details' => null,
            ]);
            $subscription->recordEvent('subscribed', ['plan' => $plan->name]);

            if ($this->cents($plan->price) > 0) {
                $this->issueInvoice($subscription, $this->cents($plan->price), 'subscription', ['event' => 'subscribed']);
            }

            return $subscription;
        });
    }

    private function upgrade(Subscription $current, SubscriptionPlan $plan, int $currentCents, int $newCents): Subscription
    {
        $mode = $this->prorationMode();
        $grace = $current->status === 'grace';
        $expired = $current->ends_at && ! $current->ends_at->isFuture();

        if ($mode === 'apply_at_renewal' && ! $grace && ! $expired) {
            return $this->deferUpgrade($current, $plan);
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($current, $plan, $currentCents, $newCents, $mode, $grace) {
            $paidThrough = $current->ends_at;
            $cycle = $plan->billing_cycle ?: $current->cycle ?: 'monthly';

            if (! $grace && $paidThrough && $paidThrough->isFuture()) {
                $endsAt = $paidThrough;
            } else {
                $endsAt = $this->cycleEnd(now(), $cycle);
            }

            $credit = ($mode === 'charge_difference' || $grace) ? 0 : ($this->proratedCredit($current, $currentCents));
            $due = $mode === 'charge_difference'
                ? max($newCents - $currentCents, 0)
                : max($newCents - $credit, 0);
            $previousPlan = $current->plan?->name;

            $details = $current->details ?? [];
            unset($details['pending_downgrade_to'], $details['pending_upgrade_to']);

            $current->update([
                'plan_id' => $plan->id,
                'status' => 'active',
                'cycle' => $cycle,
                'starts_at' => $grace || $endsAt->isFuture() ? $current->starts_at : now(),
                'ends_at' => $endsAt,
                'details' => $details ?: null,
            ]);
            $current->recordEvent('upgraded', ['to_plan' => $plan->name, 'from_plan' => $previousPlan, 'credit' => $this->money($credit), 'due' => $this->money($due)]);

            if ($due > 0) {
                $this->issueInvoice($current, $due, 'proration', ['event' => 'upgrade', 'from_plan' => $previousPlan, 'credit' => $this->money($credit)]);
            }

            return $current->fresh(['plan']);
        });
    }

    /**
     * Defer an apply_at_renewal upgrade: keep the current plan and price for
     * the rest of the cycle, then switch at the next boundary (the same
     * mechanism as a deferred downgrade).
     */
    private function deferUpgrade(Subscription $current, SubscriptionPlan $plan): Subscription
    {
        $details = $current->details ?? [];
        $details['pending_upgrade_to'] = $plan->id;

        $current->update(['details' => $details]);
        $current->recordEvent('upgrade_pending', ['to_plan' => $plan->name, 'applies_at' => $current->ends_at?->toDateString()]);

        return $current->fresh(['plan']);
    }

    private function downgrade(Subscription $current, SubscriptionPlan $plan, int $newCents): Subscription
    {
        $cycle = $plan->billing_cycle ?: $current->cycle ?: 'monthly';
        $grace = $current->status === 'grace';
        $expired = $current->ends_at && ! $current->ends_at->isFuture();

        if (! $grace && ! $expired) {
            $details = $current->details ?? [];
            unset($details['pending_upgrade_to']);
            $details['pending_downgrade_to'] = $plan->id;
            $current->update(['details' => $details]);
            $current->recordEvent('downgrade_pending', ['to_plan' => $plan->name, 'applies_at' => $current->ends_at?->toDateString()]);

            return $current->fresh(['plan']);
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($current, $plan, $newCents, $cycle) {
            $details = $current->details ?? [];
            unset($details['pending_downgrade_to'], $details['pending_upgrade_to']);

            $current->update([
                'plan_id' => $plan->id,
                'status' => 'active',
                'cycle' => $cycle,
                'starts_at' => now(),
                'ends_at' => $this->cycleEnd(now(), $cycle),
                'details' => $details ?: null,
            ]);
            $current->recordEvent('downgraded', ['to_plan' => $plan->name]);

            if ($newCents > 0) {
                $this->issueInvoice($current, $newCents, 'subscription', ['event' => 'downgraded']);
            }

            return $current->fresh(['plan']);
        });
    }

    /**
     * Same-price plan switch: apply immediately, keep the cycle anchor.
     */
    private function switchEqual(Subscription $current, SubscriptionPlan $plan): Subscription
    {
        $details = $current->details ?? [];
        unset($details['pending_downgrade_to'], $details['pending_upgrade_to']);

        $current->update([
            'plan_id' => $plan->id,
            'details' => $details ?: null,
        ]);
        $current->recordEvent('switched', ['to_plan' => $plan->name]);

        return $current->fresh(['plan']);
    }

    /**
     * The unused value of the current cycle applied as credit on an upgrade.
     * Deterministic integer-cent math; total cycle length comes from the
     * `subscriptions.cycle_days` configuration map (defaults: 30 monthly /
     * 365 annual).
     */
    public function proratedCredit(Subscription $sub, ?int $planCents = null): int
    {
        $planCents ??= $this->cents($sub->plan?->price ?? 0);
        $totalDays = $this->cycleDays($sub->cycle ?: 'monthly');
        $endsAt = $sub->ends_at?->copy() ?? now();
        $remainingSeconds = $endsAt->getTimestamp() - now()->getTimestamp();
        $remainingDays = (int) ceil($remainingSeconds / 86400);
        $remainingDays = max($remainingDays, 0);

        return (int) round(($planCents * $remainingDays) / $totalDays);
    }

    /**
     * Advance lifecycle transitions for subscriptions whose cycle/grace period
     * has lapsed: active → grace; grace → suspended (or a deferred plan change
     * — downgrade or apply_at_renewal upgrade — applies at the boundary).
     * Repeated transitions (e.g. a cycle that lapsed beyond the grace window
     * before any sweep ran) settle in one pass.
     */
    public function applyCycleEnd(Subscription $sub): void
    {
        do {
            $transitioned = false;

            if ($sub->status === 'active' && $sub->ends_at && $sub->ends_at->lte(now())) {
                $target = $this->pendingPlanTarget($sub);

                if ($target && SubscriptionPlan::find($target['plan_id'])?->status === 'active') {
                    $plan = SubscriptionPlan::find($target['plan_id']);
                    $cycle = $plan->billing_cycle ?: $sub->cycle ?: 'monthly';
                    $details = $sub->details ?? [];
                    unset($details['pending_downgrade_to'], $details['pending_upgrade_to']);

                    $sub->update([
                        'plan_id' => $target['plan_id'],
                        'status' => 'active',
                        'cycle' => $cycle,
                        'starts_at' => now(),
                        'ends_at' => $this->cycleEnd(now(), $cycle),
                        'details' => $details ?: null,
                    ]);
                    $sub->recordEvent($target['event'], ['to_plan' => (string) $plan->id, 'at' => 'cycle_end']);
                } else {
                    $details = $sub->details ?? [];
                    $details['grace_until'] = $sub->ends_at->copy()->addDays($this->graceDays())->toDateTimeString();

                    $sub->update(['status' => 'grace', 'details' => $details]);
                    $sub->recordEvent('grace_period', ['grace_until' => $details['grace_until']]);
                }

                $transitioned = true;
                continue;
            }

            if ($sub->status === 'grace') {
                $graceUntil = $sub->graceUntil();
                if ($graceUntil && $graceUntil->lte(now())) {
                    $target = $this->pendingPlanTarget($sub);

                    if ($target && SubscriptionPlan::find($target['plan_id'])?->status === 'active') {
                        $plan = SubscriptionPlan::find($target['plan_id']);
                        $cycle = $plan->billing_cycle ?: $sub->cycle ?: 'monthly';
                        $details = $sub->details ?? [];
                        unset($details['pending_downgrade_to'], $details['pending_upgrade_to']);

                        $sub->update([
                            'plan_id' => $target['plan_id'],
                            'status' => 'active',
                            'cycle' => $cycle,
                            'starts_at' => now(),
                            'ends_at' => $this->cycleEnd(now(), $cycle),
                            'details' => $details ?: null,
                        ]);
                        $sub->recordEvent($target['event'], ['to_plan' => (string) $plan->id, 'at' => 'grace_end']);
                    } else {
                        $sub->update(['status' => 'suspended']);
                        $sub->recordEvent('suspended');
                    }

                    $transitioned = true;
                }
            }
        } while ($transitioned);
    }

    /**
     * Resolve the deferred plan change pending at a boundary. An
     * apply_at_renewal upgrade and a deferred downgrade both settle here, so
     * the new plan applies exactly when the current cycle ends.
     *
     * @return array{plan_id: int, event: string}|null
     */
    private function pendingPlanTarget(Subscription $sub): ?array
    {
        $details = $sub->details ?? [];

        foreach (['pending_upgrade_to', 'pending_downgrade_to'] as $key) {
            if (isset($details[$key])) {
                return [
                    'plan_id' => (int) $details[$key],
                    'event' => $key === 'pending_upgrade_to' ? 'upgraded' : 'downgraded',
                ];
            }
        }

        return null;
    }

    /**
     * Sweep every lapsed active/grace subscription through the lifecycle.
     */
    public function runExpiryCheck(): void
    {
        Subscription::whereIn('status', ['active', 'grace'])->get()
            ->each(function (Subscription $sub) {
                $this->applyCycleEnd($sub);
            });
    }

    /**
     * Raise a settled invoice + receipt for a subscription (billing gateway
     * lands with M9; settlement is recorded here so queues never create
     * stale pending rows).
     */
    private function issueInvoice(Subscription $sub, int $amountCents, string $event, array $details = []): SubscriptionInvoice
    {
        $amount = $this->money($amountCents);
        $year = now()->year;
        $prefix = (string) $this->config->get('numbering.invoice.prefix', 'SUB');
        $padding = (int) $this->config->get('numbering.invoice.padding', 4);
        $start = (int) $this->config->get('numbering.invoice.start', 1);
        $yearReset = (bool) $this->config->get('numbering.invoice.year_reset', true);
        $receiptPrefix = (string) $this->config->get('numbering.receipt.prefix', 'RCT');
        $receiptLength = (int) $this->config->get('numbering.receipt.length', 8);

        $yearPrefix = $yearReset ? "$prefix-$year-" : "$prefix-";
        $sequenceBase = SubscriptionInvoice::where('invoice_no', 'like', "$yearPrefix%")->count() + $start;

        do {
            $sequence = Str::padLeft((string) $sequenceBase, $padding, '0');
            $invoiceNo = $yearPrefix . $sequence;
            $receiptNo = $receiptPrefix . '-' . Str::upper(Str::random($receiptLength));
            $sequenceBase++;
        } while (SubscriptionInvoice::where('invoice_no', $invoiceNo)->exists());

        return $sub->invoices()->create([
            'amount' => $amount,
            'status' => 'paid',
            'invoice_no' => $invoiceNo,
            'receipt_no' => $receiptNo,
            'paid_at' => now(),
            'details' => array_merge($details, ['event' => $event]),
        ]);
    }

    /**
     * Parse a decimal string (or float) into integer cents.
     */
    private function cents($value): int
    {
        return (int) round(((float) $value) * 100);
    }

    /**
     * Format integer cents as a fixed 2-dp money string for DECIMAL columns.
     */
    public function money(int $cents): string
    {
        return sprintf('%0.2f', $cents / 100);
    }

    /**
     * Default end-of-cycle timestamp for a given billing cycle, from the
     * `subscriptions.cycle_days` configuration map.
     */
    private function cycleEnd(Carbon $from, string $cycle = 'monthly'): Carbon
    {
        return $from->copy()->addDays($this->cycleDays($cycle));
    }

    /**
     * Length in days of a billing cycle (config-driven).
     */
    private function cycleDays(string $cycle): int
    {
        $map = $this->config->get('subscriptions.cycle_days', ['monthly' => 30, 'annual' => 365]);

        if (is_array($map) && isset($map[$cycle])) {
            return (int) $map[$cycle];
        }

        return (int) ($map['monthly'] ?? 30);
    }

    /**
     * Days a subscription may stay in grace after its cycle ends
     * (`subscriptions.grace_period_days`).
     */
    public function graceDays(): int
    {
        return (int) $this->config->get('subscriptions.grace_period_days', 7);
    }

    private function freeLimit(): int
    {
        $defaultPlanName = $this->config->get('subscriptions.default_plan', 'Free');

        return SubscriptionPlan::where('name', $defaultPlanName)->value('listing_limit') ?? 1;
    }
}