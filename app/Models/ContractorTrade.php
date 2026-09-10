<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One trade a contractor offers, with their (optional) rate (Module 11).
 */
class ContractorTrade extends Model
{
    use HasFactory;

    protected $fillable = [
        'contractor_id',
        'trade',
        'rate',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
        ];
    }

    public function contractor()
    {
        return $this->belongsTo(Contractor::class);
    }
}