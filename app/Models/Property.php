<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'owner_id',
        'title',
        'description',
        'property_type',
        'bedrooms',
        'bathrooms',
        'building_size',
        'land_size',
        'price',
        'deposit',
        'furnished',
        'status',
        'suburb',
        'zone',
        'city',
        'address',
        'amenities',
        'cover_image',
        'featured',
        'verified',
        'available_from',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'deposit' => 'decimal:2',
            'building_size' => 'decimal:2',
            'land_size' => 'decimal:2',
            'furnished' => 'boolean',
            'featured' => 'boolean',
            'verified' => 'boolean',
            'amenities' => 'array',
            'available_from' => 'date',
        ];
    }

    /**
     * The property type labels used across the UI.
     */
    public const TYPES = [
        'house' => 'House',
        'flat' => 'Flat / Apartment',
        'townhouse' => 'Townhouse',
        'cottage' => 'Cottage',
        'room' => 'Room',
        'commercial' => 'Commercial',
        'land' => 'Land',
    ];

    /**
     * The property status labels used across the UI.
     */
    public const STATUSES = [
        'available' => 'Available',
        'reserved' => 'Reserved',
        'occupied' => 'Occupied',
        'unavailable' => 'Unavailable',
    ];

    /**
     * Allowed status transitions (explicit state machine).
     * A status may only move to one of the listed targets.
     */
    public const TRANSITIONS = [
        'available' => ['reserved', 'unavailable'],
        'reserved' => ['occupied', 'available'],
        'occupied' => ['available', 'unavailable'],
        'unavailable' => ['available'],
    ];

    /**
     * Determine whether the property can move to the given status.
     */
    public function canTransitionTo(string $newStatus): bool
    {
        return in_array($newStatus, self::TRANSITIONS[$this->status] ?? [], true);
    }

    /**
     * Get the owner (landlord) of the property.
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get the tenants that have favourited this property.
     */
    public function favouritedBy()
    {
        return $this->belongsToMany(User::class, 'property_favourites', 'property_id', 'user_id')
            ->withTimestamps();
    }

    /**
     * Get the rental applications for this property.
     */
    public function applications()
    {
        return $this->hasMany(RentalApplication::class);
    }

    /**
     * Get the leases generated against this property.
     */
    public function leases()
    {
        return $this->hasMany(Lease::class);
    }

    /**
     * Get the tenant enquiries about this property.
     */
    public function enquiries()
    {
        return $this->hasMany(Enquiry::class);
    }

    /**
     * Get the available viewing slots for this property.
     */
    public function viewingSlots()
    {
        return $this->hasMany(ViewingSlot::class);
    }

    /**
     * Get the viewing requests against this property.
     */
    public function viewingRequests()
    {
        return $this->hasMany(ViewingRequest::class);
    }

    /**
     * Get the gallery images for this property.
     */
    public function images()
    {
        return $this->hasMany(PropertyImage::class)->orderBy('sort_order');
    }

    /**
     * Get the status-change history for this property.
     */
    public function history()
    {
        return $this->hasMany(PropertyHistory::class)->latest();
    }

    /**
     * Scope to only published (available) listings.
     */
    public function scopeListed($query)
    {
        return $query->where('status', 'available');
    }

    /**
     * Scope to verified properties only.
     */
    public function scopeVerified($query)
    {
        return $query->where('verified', true);
    }

    /**
     * Scope to featured properties only.
     */
    public function scopeFeatured($query)
    {
        return $query->where('featured', true);
    }
}