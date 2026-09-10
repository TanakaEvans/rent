<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'lease_id',
        'type',
        'name',
        'content',
        'mime',
        'size',
        'version',
        'visibility',
    ];

    /**
     * The attribute casts applied to this model.
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'version' => 'integer',
        ];
    }

    /**
     * The lease this document records (e.g. a signed lease agreement).
     */
    public function lease()
    {
        return $this->belongsTo(Lease::class);
    }
}