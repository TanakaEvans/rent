<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemModule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'prefix',
        'icon',
        'description',
        'order',
        'status',
    ];

    public function routes()
    {
        return $this->hasMany(SystemRoute::class);
    }

    public function scopeEnabled($query)
    {
        return $query->where('status', 'Enabled');
    }
}
