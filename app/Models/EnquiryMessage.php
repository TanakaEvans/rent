<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EnquiryMessage extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'enquiry_id',
        'sender_id',
        'sender_role',
        'body',
    ];

    /**
     * Who can write on an enquiry thread.
     */
    public const ROLES = ['owner', 'tenant'];

    /**
     * The enquiry thread this message belongs to.
     */
    public function enquiry()
    {
        return $this->belongsTo(Enquiry::class);
    }

    /**
     * The user who wrote the message.
     */
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
