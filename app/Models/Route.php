<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Route extends Model
{
    use HasFactory;

    protected $table = 'auth_routes';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'uri',
        'methods',
        'action',
        'module',
        'description',
        'priority',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => 'integer',
        ];
    }

    /**
     * Get the roles that have access to this route.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'auth_role_routes', 'route_id', 'role_id')
            ->withTimestamps();
    }

    /**
     * Scope for finding routes by module
     */
    public function scopeByModule($query, string $module)
    {
        return $query->where('module', $module);
    }

    /**
     * Scope for ordering routes by priority
     */
    public function scopeOrderedByPriority($query)
    {
        return $query->orderBy('priority')->orderBy('name');
    }

    /**
     * Get routes grouped by module
     */
    public static function getRoutesByModule()
    {
        return self::orderBy('module')
            ->orderBy('priority')
            ->orderBy('name')
            ->get()
            ->groupBy('module')
            ->map(function ($moduleRoutes) {
                return $moduleRoutes->sortBy('priority')->values();
            });
    }
}
