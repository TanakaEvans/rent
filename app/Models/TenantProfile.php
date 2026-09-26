<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantProfile extends Model
{
    /**
     * Accepted employment situations. Catalogue values, not free text.
     */
    public const EMPLOYMENT_STATUSES = ['employed', 'self_employed', 'student', 'unemployed', 'retired'];

    /**
     * Accepted monthly income bands (USD). Kept coarse — used by owners during
     * application review and by the net-tenancy scoring groundwork (S9).
     */
    public const SALARY_BANDS = ['under_250', '250_to_500', '501_to_1000', '1000_to_2000', 'over_2000'];

    /**
     * How the tenant prefers to be reached (mirrors the property-side
     * `contact_preference` catalogue: platform/phone/whatsapp).
     */
    public const PREFERRED_CONTACTS = ['platform', 'phone', 'whatsapp'];

    protected $fillable = [
        'user_id',
        'phone',
        'city',
        'employment_status',
        'salary_band',
        'preferred_contact',
        'about',
    ];

    /**
     * The tenant account this profile belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}