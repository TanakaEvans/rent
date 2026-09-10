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
        'password',
        'status',
        'password_changed_at',
        'password_expires_at',
        'failed_login_attempts',
        'locked_at',
        'verified',
        'verified_at',
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
        ];
    }

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
