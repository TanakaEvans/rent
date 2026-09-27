<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'auth_users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'username',
        'avatar_path',
        'password',
        'status',
        'password_changed_at',
        'password_expires_at',
        'failed_login_attempts',
        'locked_at',
        'verified',
        'verified_at',
        'badge_tier',
        'rating_avg',
        'ratings_count',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'password_changed_at' => 'datetime',
            'password_expires_at' => 'datetime',
            'locked_at' => 'datetime',
            'verified' => 'boolean',
            'verified_at' => 'datetime',
            'badge_tier' => 'string',
            'rating_avg' => 'decimal:2',
            'ratings_count' => 'integer',
        ];
    }

    /**
     * Serialized so every signed-in page can show the profile photo and a
     * fallback monogram without a second query.
     *
     * @var array<int, string>
     */
    protected $appends = ['avatar_url', 'initials'];

    /**
     * Public URL of the profile photo, or null when none is set (the UI then
     * shows the monogram). Photos live on the public disk.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        $path = $this->attributes['avatar_path'] ?? null;

        return $path ? asset('storage/'.$path) : null;
    }

    /**
     * One or two letters for the avatar fallback, e.g. "Tendai Moyo" → "TM".
     */
    public function getInitialsAttribute(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];
        $letters = array_map(fn ($part) => mb_substr($part, 0, 1), array_slice($parts, 0, 2));

        return mb_strtoupper(implode('', $letters)) ?: 'U';
    }

    /**
     * Accepted owner badge tiers (Preservation-Release S2/S5). Tiers derive
     * from the KYC tier + verification decisions made in S5 — none by default.
     */
    public const BADGE_TIERS = ['none', 'bronze', 'silver', 'gold'];

    /**
     * Get the roles assigned to the user.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'auth_user_roles', 'user_id', 'role_id')
            ->withPivot('assigned_by')
            ->withTimestamps();
    }

    /**
     * Check if user has a specific role
     */
    public function hasRole(string $roleName): bool
    {
        return $this->roles()->where('name', $roleName)->exists();
    }

    /**
     * Check if user has any of the specified roles
     */
    public function hasAnyRole(array $roles): bool
    {
        return $this->roles()->whereIn('name', $roles)->exists();
    }

    /**
     * Assign a role to the user
     */
    public function assignRole(string $roleName, ?int $assignedBy = null): void
    {
        $role = Role::where('name', $roleName)->first();
        if ($role && ! $this->hasRole($roleName)) {
            $this->roles()->attach($role->id, [
                'assigned_by' => $assignedBy,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Remove a role from the user
     */
    public function removeRole(string $roleName): void
    {
        $role = Role::where('name', $roleName)->first();
        if ($role) {
            $this->roles()->detach($role->id);
        }
    }

    /**
     * Scope for active users
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Get the employee profile for this user
     */
    public function employee()
    {
        return $this->hasOne(Employee::class);
    }

    /**
     * Get the properties owned by this user (landlord).
     */
    public function properties()
    {
        return $this->hasMany(Property::class, 'owner_id');
    }

    /**
     * Get the tenant profile for this user (S4 — one per tenant account).
     */
    public function tenantProfile()
    {
        return $this->hasOne(TenantProfile::class, 'user_id');
    }

    /**
     * Get the tenant's identity-evidence scans (S4 KYC, private disk).
     */
    public function identityDocuments()
    {
        return $this->hasMany(IdentityDocument::class, 'user_id');
    }

    /**
     * Get the tenant's identity-evidence audit trail (S4).
     */
    public function identityDocumentAudits()
    {
        return $this->hasMany(IdentityDocumentAudit::class, 'user_id');
    }

    /**
     * Get the properties favourited by this user (tenant).
     */
    public function favouritedProperties()
    {
        return $this->belongsToMany(Property::class, 'property_favourites', 'user_id', 'property_id')
            ->withTimestamps();
    }

    /**
     * Get the rental applications submitted by this user (tenant).
     */
    public function rentalApplications()
    {
        return $this->hasMany(RentalApplication::class, 'applicant_id');
    }

    /**
     * Get the property enquiries sent by this user (tenant).
     */
    public function enquiries()
    {
        return $this->hasMany(Enquiry::class, 'tenant_id');
    }

    /**
     * Get the viewing requests sent by this user (tenant).
     */
    public function viewingRequests()
    {
        return $this->hasMany(ViewingRequest::class, 'tenant_id');
    }

    /**
     * Get the tenant's saved marketplace searches (Module 04 Phase 2).
     */
    public function savedSearches()
    {
        return $this->hasMany(SavedSearch::class);
    }

    /**
     * Get the marketplace reports raised by this user.
     */
    public function reports()
    {
        return $this->hasMany(Report::class, 'reporter_id');
    }

    /**
     * Get every subscription this user has held (owners).
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'owner_id');
    }

    /**
     * Get the owner's most recent subscription (or none).
     */
    public function currentSubscription()
    {
        return $this->subscriptions()->latest('id')->first();
    }

    /**
     * Check if user has access to a specific route
     */
    public function hasRouteAccess(string $routeName): bool
    {
        // Superuser has access to everything
        if ($this->hasRole('Superuser')) {
            return true;
        }

        // Check if any of the user's roles has access to this route
        foreach ($this->roles as $role) {
            if ($role->hasRouteAccess($routeName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all accessible routes for this user
     */
    public function getAccessibleRoutes(): array
    {
        if ($this->hasRole('Superuser')) {
            return SystemRoute::pluck('name')->toArray();
        }

        $routes = [];
        foreach ($this->roles as $role) {
            $routes = array_merge($routes, $role->systemRoutes->pluck('name')->toArray());
        }

        return array_unique($routes);
    }
}
