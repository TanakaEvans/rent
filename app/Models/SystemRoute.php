<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemRoute extends Model
{
    use HasFactory;

    protected $fillable = [
        'system_module_id',
        'name',
        'uri',
        'description',
        'status',
    ];

    public function module()
    {
        return $this->belongsTo(SystemModule::class, 'system_module_id');
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_routes');
    }
}
