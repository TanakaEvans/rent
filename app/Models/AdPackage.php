<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdPackage extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'code',
        'placement_type',
        'price',
        'duration_days',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'duration_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function placements()
    {
        return $this->hasMany(AdPlacement::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}