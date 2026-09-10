<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Property;
use App\Models\RentInvoice;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Landlord income / arrears / occupancy aggregation (Module 09 FR-07,
 * Wave 4 slice 5). Feeds the owner dashboard and rent page. Every figure is
 * computed server-side straight from the immutable ledger (AC-04) — never
 * client arithmetic — and always scoped to the requesting owner's properties.
 */
final class FinancialSummaryService
{
    public function __construct(private readonly RentService $rent)
    {
    }

    /**
     * The full financial snapshot for one owner: income this month + a six
     * month income trend, what tenants still owe (outstanding = due + overdue
     * amounts + accrued late fees), the arrears subset and portfolio
     * occupancy. `income` counts only settled payments whose receipt date
     * (`paid_at`) falls inside the month.
     */
    public function ownerIndex(User $owner, ?Carbon $month = null): array
    {
        $month = $month?->copy() ?? Carbon::now();

        $invoices = RentInvoice::whereHas('property', fn ($query) => $query->where('owner_id', $owner->id))->get();
        $due = $invoices->where('status', 'due');
        $overdue = $invoices->where('status', 'overdue');

        $settledMonth = $this->settledBetween($owner, $month->copy()->startOfMonth(), $month->copy()->endOfMonth());

        $properties = Property::where('owner_id', $owner->id)->get();
        $occupied = $properties->where('status', 'occupied')->count();

        return [
            'monthly_income' => $this->money($settledMonth->sum('amount')),
            'income_trend' => $this->incomeTrend($owner, 6),
            'rent_due' => $this->money($due->sum('amount') + $overdue->sum('amount') + $overdue->sum('late_fee')),
            'outstanding_total' => $this->money($due->sum('amount') + $overdue->sum('amount') + $overdue->sum('late_fee')),
            'arrears_total' => $this->money($overdue->sum('amount') + $overdue->sum('late_fee')),
            'late_fees_total' => $this->money($overdue->sum('late_fee')),
            'occupancy_rate' => $this->occupancyRate($properties->count(), $occupied),
            'occupied_properties' => $occupied,
            'total_properties' => $properties->count(),
        ];
    }

    /**
     * Settled rental income across the requested owner's properties, one
     * bucket per month (oldest first).
     *
     * @return array<int, array{month: string, label: string, income: string}>
     */
    private function incomeTrend(User $owner, int $months): array
    {
        $trend = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $period = Carbon::now()->subMonths($i);
            $total = $this->settledBetween($owner, $period->copy()->startOfMonth(), $period->copy()->endOfMonth())->sum('amount');
            $trend[] = [
                'month' => $period->format('Y-m'),
                'label' => $period->format('M'),
                'income' => $this->money($total),
            ];
        }

        return $trend;
    }

    private function settledBetween(User $owner, Carbon $from, Carbon $to)
    {
        return Payment::where('status', 'settled')
            ->whereBetween('paid_at', [$from, $to])
            ->whereHas('invoice.property', fn ($query) => $query->where('owner_id', $owner->id))
            ->get();
    }

    private function occupancyRate(int $total, int $occupied): float
    {
        if ($total === 0) {
            return 0.0;
        }

        return round(($occupied / $total) * 100, 1);
    }

    private function money(float|string|int $value): string
    {
        return sprintf('%01.2f', (float) $value);
    }
}