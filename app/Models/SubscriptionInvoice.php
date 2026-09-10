<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionInvoice extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'subscription_id',
        'amount',
        'status',
        'invoice_no',
        'receipt_no',
        'paid_at',
        'details',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => 'string',
            'paid_at' => 'datetime',
            'details' => 'array',
        ];
    }

    /**
     * The subscription this invoice belongs to.
     */
    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }
}