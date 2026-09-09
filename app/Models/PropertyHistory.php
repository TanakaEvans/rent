<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropertyHistory extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'property_history';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'property_id',
        'from_status',
        'to_status',
        'changed_by',
    ];

    /**
     * The property this history entry belongs to.
     */
    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * The user who performed the status change.
     */
    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}