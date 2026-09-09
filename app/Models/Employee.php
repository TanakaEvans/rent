<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'branch_id',
        'department_id',
        'employee_number',
        'first_name',
        'last_name',
        'middle_name',
        'gender',
        'date_of_birth',
        'national_id',
        'email',
        'phone',
        'alt_phone',
        'address',
        'city',
        'job_title',
        'hire_date',
        'termination_date',
        'employment_type',
        'salary',
        'bank_name',
        'bank_account',
        'emergency_contact_name',
        'emergency_contact_phone',
        'photo',
        'status',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'hire_date' => 'date',
        'termination_date' => 'date',
        'salary' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function getFullNameAttribute()
    {
        return trim("{$this->first_name} {$this->middle_name} {$this->last_name}");
    }

    public function hasUserAccount()
    {
        return $this->user_id !== null;
    }
}
