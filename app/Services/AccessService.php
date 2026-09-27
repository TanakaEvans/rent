<?php

namespace App\Services;

use App\Models\AccessPass;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * The configurable free-then-paid access model (Module 24 — `access.*`).
 *
 * Every rule is data, read live from the Configuration Centre:
 *   - `access.charge_enabled` — master switch (default false = free for all).
 *   - `access.free_until`     — everyone is free up to and including this date.
 *   - `access.payer`          — who must hold a pass once free ends (owner|tenant|both).
 *   - `access.price` / `access.currency` / `access.period_days` — what a pass costs and lasts.
 *
 * While charging is off (the shipped default) {@see requiresPass()} is always
 * false, so {@see hasActiveAccess()} is always true and the gate middleware is
 * a complete no-op — nobody can be locked out.
 */
class AccessService
{
    public function __construct(private readonly ConfigurationService $config)
    {
    }

    /**
     * Whether this user must hold an active access pass right now. True only
     * when charging is enabled AND today is past the free-until date AND the
     * user's role is the configured payer.
     */
    public function requiresPass(User $user): bool
    {
        if (! $this->chargeEnabled()) {
            return false;
        }

        if (! $this->freeWindowClosed()) {
            return false;
        }

        return $this->userIsPayer($user);
    }

    /**
     * Whether the user may perform gated actions: true when no pass is required
     * of them, or when they hold a pass that has not yet expired.
     */
    public function hasActiveAccess(User $user): bool
    {
        if (! $this->requiresPass($user)) {
            return true;
        }

        return $this->activePass($user) !== null;
    }

    /**
     * The user's current unexpired pass, or null.
     */
    public function activePass(User $user): ?AccessPass
    {
        return AccessPass::query()
            ->where('user_id', $user->id)
            ->where('ends_at', '>', now())
            ->latest('ends_at')
            ->first();
    }

    /**
     * A UI-friendly snapshot of the user's access position.
     *
     * @return array{required: bool, active: bool, free_until: ?string, price: string, currency: string, period_days: int, ends_at: ?string}
     */
    public function status(User $user): array
    {
        $required = $this->requiresPass($user);
        $pass = $this->activePass($user);

        return [
            'required' => $required,
            'active' => ! $required || $pass !== null,
            'free_until' => $this->config->get('access.free_until'),
            'price' => number_format((float) $this->config->get('access.price', 0), 2, '.', ''),
            'currency' => (string) $this->config->get('access.currency', 'USD'),
            'period_days' => (int) $this->config->get('access.period_days', 30),
            'ends_at' => $pass?->ends_at?->toIso8601String(),
        ];
    }

    /**
     * Record a paid access pass for the user (test mode: no gateway — the pass
     * is created immediately, exactly like the subscription flow issues settled
     * invoices without a gateway). Extends from the end of any still-active pass
     * so buying early never shortens access.
     */
    public function purchase(User $user): AccessPass
    {
        $days = max(1, (int) $this->config->get('access.period_days', 30));
        $price = number_format((float) $this->config->get('access.price', 0), 2, '.', '');
        $currency = (string) $this->config->get('access.currency', 'USD');

        $active = $this->activePass($user);
        $base = $active && $active->ends_at->isFuture() ? $active->ends_at->copy() : now();

        return AccessPass::create([
            'user_id' => $user->id,
            'starts_at' => now(),
            'ends_at' => $base->addDays($days),
            'amount' => $price,
            'currency' => $currency,
            'reference' => 'ACCESS-'.strtoupper(Str::random(10)),
        ]);
    }

    private function chargeEnabled(): bool
    {
        return (bool) $this->config->get('access.charge_enabled', false);
    }

    /**
     * True once today is strictly past the configured free-until date.
     */
    private function freeWindowClosed(): bool
    {
        $freeUntil = $this->config->get('access.free_until');
        if (empty($freeUntil)) {
            return true;
        }

        return Carbon::now()->startOfDay()->greaterThan(Carbon::parse($freeUntil)->endOfDay());
    }

    /**
     * Whether the user's role falls under the configured payer setting.
     */
    private function userIsPayer(User $user): bool
    {
        return match ((string) $this->config->get('access.payer', 'owner')) {
            'owner' => $user->hasRole('Owner'),
            'tenant' => $user->hasRole('Tenant'),
            'both' => $user->hasAnyRole(['Owner', 'Tenant']),
            default => false,
        };
    }
}
