<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RentSchedule extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'lease_id',
        'start_date',
        'end_date',
        'rent_amount',
        'payment_terms',
    ];

    /**
     * The attribute casts applied to this model.
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'rent_amount' => 'decimal:2',
            'payment_terms' => 'array',
        ];
    }

    /**
     * The lease this schedule bills for.
     */
    public function lease()
    {
        return $this->belongsTo(Lease::class);
    }

    /**
     * The invoices raised for this schedule, oldest period first.
     */
    public function invoices()
    {
        return $this->hasMany(RentInvoice::class, 'schedule_id')->orderBy('period_start');
    }

    /**
     * The invoices still unpaid (not settled and not void).
     */
    public function outstandingInvoices()
    {
        return $this->invoices()->whereIn('status', ['draft', 'due', 'overdue']);
    }
}