<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    public const TYPES = ['city', 'suburb', 'landmark'];

    public const DENSITIES = ['low', 'medium', 'high', 'industrial', 'commercial'];

    public const CATEGORIES = ['shopping', 'education', 'health', 'transport', 'recreation', 'market', 'business'];

    public const PROVINCES = [
        'Bulawayo',
        'Harare',
        'Manicaland',
        'Mashonaland Central',
        'Mashonaland East',
        'Mashonaland West',
        'Masvingo',
        'Matabeleland North',
        'Matabeleland South',
        'Midlands',
    ];

    protected $fillable = [
        'type',
        'name',
        'province',
        'city',
        'zone',
        'suburb',
        'density',
        'category',
        'latitude',
        'longitude',
        'is_popular',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'is_popular' => 'boolean',
    ];

    public function scopeCities(Builder $query): Builder
    {
        return $query->where('type', 'city');
    }

    public function scopeSuburbs(Builder $query): Builder
    {
        return $query->where('type', 'suburb');
    }

    public function scopeLandmarks(Builder $query): Builder
    {
        return $query->where('type', 'landmark');
    }

    public function scopeInCity(Builder $query, string $city): Builder
    {
        return $query->where('city', $city);
    }

    public function scopePopular(Builder $query): Builder
    {
        return $query->where('is_popular', true);
    }
}
