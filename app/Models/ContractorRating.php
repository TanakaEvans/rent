<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * An owner's score for a contractor after a closed maintenance job
 * (Module 11, FR-04 / AC-02). One rating per request — the unique
 * request_id makes double-rating impossible.
 */
class ContractorRating extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'request_id',
        'contractor_id',
        'owner_id',
        'rating',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
        ];
    }

    /**
     * The maintenance job this rating belongs to.
     */
    public function request()
    {
        return $this->belongsTo(MaintenanceRequest::class, 'request_id');
    }

    /**
     * The contractor who was scored.
     */
    public function contractor()
    {
        return $this->belongsTo(Contractor::class);
    }

    /**
     * The property owner who left the score (may be removed later).
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}