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
        'floor_area',
        'year_built',
        'price',
        'deposit',
        'currency',
        'payment_terms',
        'water_cost',
        'electricity_cost',
        'trash_cost',
        'negotiable',
        'furnished',
        'entrance_type',
        'bathroom_type',
        'parking_type',
        'families_allowed',
        'distance_to_cbd',
        'security_type',
        'children_allowed',
        'pets_allowed',
        'smoking_allowed',
        'parties_allowed',
        'minimum_stay',
        'preferred_tenant',
        'landlord_type',
        'contact_preference',
        'show_phone',
        'landmark',
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
        'latitude',
        'longitude',
        'expires_at',
        'expiry_reminder_sent_days',
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
            'floor_area' => 'decimal:2',
            'year_built' => 'integer',
            'water_cost' => 'decimal:2',
            'electricity_cost' => 'decimal:2',
            'trash_cost' => 'decimal:2',
            'distance_to_cbd' => 'decimal:2',
            'negotiable' => 'boolean',
            'furnished' => 'boolean',
            'families_allowed' => 'boolean',
            'children_allowed' => 'boolean',
            'pets_allowed' => 'boolean',
            'smoking_allowed' => 'boolean',
            'parties_allowed' => 'boolean',
            'show_phone' => 'boolean',
            'featured' => 'boolean',
            'verified' => 'boolean',
            'amenities' => 'array',
            'available_from' => 'date',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'expires_at' => 'datetime',
            'expiry_reminder_sent_days' => 'integer',
        ];
    }

    /**
     * The property type labels used across the UI (Preservation-release
     * taxonomy: Rooms, Flat, Apartment, Full-house, Cottage).
     */
    public const TYPES = [
        'room' => 'Room',
        'flat' => 'Flat',
        'apartment' => 'Apartment',
        'house' => 'Full house',
        'townhouse' => 'Townhouse',
        'cottage' => 'Cottage',
        'commercial' => 'Commercial',
        'land' => 'Land',
    ];

    /**
     * Accepted pricing currencies.
     */
    public const CURRENCIES = ['USD', 'ZWL'];

    /**
     * Accepted rent payment cadence.
     */
    public const PAYMENT_TERMS = ['monthly', 'quarterly', 'yearly'];

    /**
     * Accepted parking outcomes on a room/listing.
     */
    public const PARKING_TYPES = ['none', 'street', 'secure'];

    /**
     * Accepted security descriptors for a listing.
     */
    public const SECURITY_TYPES = ['gated', 'fenced', 'none'];

    /**
     * Accepted preferred-tenant audiences.
     */
    public const PREFERRED_TENANTS = ['any', 'family', 'single', 'professionals', 'students'];

    /**
     * Accepted landlord-on-listing types.
     */
    public const LANDLORD_TYPES = ['direct', 'agent', 'corporate'];

    /**
     * Accepted owner contact preferences.
     */
    public const CONTACT_PREFERENCES = ['platform', 'whatsapp', 'call', 'email'];

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
     * Get the tenants' express-interest rows for this property.
     */
    public function interests()
    {
        return $this->hasMany(ExpressInterest::class);
    }

    /**
     * Get the gallery images for this property.
     */
    public function images()
    {
        return $this->hasMany(PropertyImage::class)->orderBy('sort_order');
    }

    /**
     * Get the marketplace views for this property.
     */
    public function views()
    {
        return $this->hasMany(PropertyView::class);
    }

    /**
     * Get the marketplace reports raised against this property.
     */
    public function reports()
    {
        return $this->hasMany(Report::class, 'subject_id')->where('subject_type', 'property');
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