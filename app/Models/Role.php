<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use HasFactory;

    protected $table = 'auth_roles';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
    ];

    /**
     * Get the users assigned to this role.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'auth_user_roles', 'role_id', 'user_id')
            ->withPivot('assigned_by')
            ->withTimestamps();
    }

    /**
     * Get the system routes that this role has access to.
     */
    public function systemRoutes(): BelongsToMany
    {
        return $this->belongsToMany(SystemRoute::class, 'role_routes', 'role_id', 'system_route_id')
            ->withTimestamps();
    }

    /**
     * Check if role has access to a specific route
     */
    public function hasRouteAccess(string $routeName): bool
    {
        return $this->systemRoutes()->where('name', $routeName)->exists();
    }

    /**
     * Check if this is the superuser role
     */
    public function isSuperuser(): bool
    {
        return $this->name === 'Superuser';
    }

    /**
     * Scope for finding role by name
     */
    public function scopeByName($query, string $name)
    {
        return $query->where('name', $name);
    }

    /**
     * Check if role has users
     */
    public function hasUsers(): bool
    {
        return $this->users()->exists();
    }

    /**
     * Get users count for this role
     */
    public function getUsersCount(): int
    {
        return $this->users()->count();
    }

    /**
     * Get active users count for this role
     */
    public function getActiveUsersCount(): int
    {
        return $this->users()->where('status', 'active')->count();
    }
}
