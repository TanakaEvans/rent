<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropertyFavourite extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'property_id',
    ];

    /**
     * Get the user who favourited the property.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the favourited property.
     */
    public function property()
    {
        return $this->belongsTo(Property::class);
    }
}