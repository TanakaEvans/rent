<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'trading_name',
        'registration_number',
        'tax_number',
        'email',
        'phone',
        'website',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'logo',
        'currency',
        'status',
        'head_id',
    ];

    public function branches()
    {
        return $this->hasMany(Branch::class);
    }

    public function mainBranch()
    {
        return $this->hasOne(Branch::class)->where('is_main_branch', true);
    }

    public function employees()
    {
        return $this->hasManyThrough(Employee::class, Branch::class);
    }

    public function head()
    {
        return $this->belongsTo(Employee::class, 'head_id');
    }
}
