<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Section extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'department_id',
        'head_id', // Assuming nullable foreign key to Employee
        'status',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function head()
    {
        return $this->belongsTo(Employee::class, 'head_id');
    }

    public function employees()
    {
        return $this->hasMany(Employee::class); // Make sure Employee has section_id if using this
        // Actually, Employee model I saw earlier didn't have section_id in fillable.
        // But ndaramaerp controller used section_id.
        // Check Employee model again? I saw it before.
    }
}
