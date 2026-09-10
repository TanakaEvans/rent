<?php

namespace App\Http\Controllers;

use App\Services\PaymentService;
use App\Services\RentService;
use Inertia\Inertia;

class RentController extends Controller
{
    public function __construct(
        private readonly RentService $rent,
        private readonly PaymentService $payments,
    ) {
    }

    /**
     * The owner's rent & income view: a schedule card per active lease with
     * its invoices, the invoiced/due/overdue totals and the arrear statement
     * of every unpaid invoice across their properties.
     */
    public function ownerIndex()
    {
        $owner = request()->user();

        return Inertia::render('Owner/Rent/Index', [
            'schedules' => $this->rent->schedulesForOwner($owner),
            'summary' => $this->rent->ownerSummary($owner),
            'statement' => $this->rent->ownerStatement($owner),
        ]);
    }

    /**
     * The tenant's rent view: their invoices across every lease with what is
     * due, the payment rules and their personal arrear statement.
     */
    public function tenantIndex()
    {
        $tenant = request()->user();

        return Inertia::render('Tenant/Rent', [
            'invoices' => $this->rent->invoicesForTenant($tenant),
            'summary' => $this->rent->tenantSummary($tenant),
            'statement' => $this->rent->tenantStatement($tenant),
            'rules' => $this->payments->paymentRules(),
        ]);
    }
}