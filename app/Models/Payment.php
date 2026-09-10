<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'invoice_id',
        'paid_by',
        'received_by',
        'amount',
        'method',
        'reference',
        'pop_path',
        'receipt_no',
        'status',
        'paid_at',
    ];

    /**
     * The attribute casts applied to this model.
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * The rent payment status labels used across the UI.
     */
    public const STATUSES = [
        'pending' => 'Pending',
        'settled' => 'Settled',
        'rejected' => 'Rejected',
        'refunded' => 'Refunded',
    ];

    /**
     * Allowed status transitions (explicit state machine). A settled payment
     * can only move to refunded; a rejected payment is a void attempt and the
     * invoice returns to owing.
     */
    public const TRANSITIONS = [
        'pending' => ['settled', 'rejected'],
        'settled' => ['refunded'],
        'rejected' => [],
        'refunded' => [],
    ];

    /**
     * Determine whether the payment can move to the given status.
     */
    public function canTransitionTo(string $newStatus): bool
    {
        return in_array($newStatus, self::TRANSITIONS[$this->status] ?? [], true);
    }

    /**
     * The rent invoice this payment settles.
     */
    public function invoice()
    {
        return $this->belongsTo(RentInvoice::class);
    }

    /**
     * The tenant who paid.
     */
    public function paidBy()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    /**
     * The staff member who approved / recorded the settlement (nullable).
     */
    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}