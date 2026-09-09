<?php

return [

    /*
    |--------------------------------------------------------------------------
    | TTCS Configuration
    |--------------------------------------------------------------------------
    |
    | System-wide settings for the Transformer Testing and Certification
    | Management System. Values here can be overridden per-environment.
    |
    */

    // Default approval tier required for newly created testing jobs.
    'approval_tier_default' => env('TTCS_APPROVAL_TIER_DEFAULT', 'single'),

    // When enabled, a user cannot approve a testing job they created.
    'enforce_separation_of_duties' => env('TTCS_ENFORCE_SEPARATION_OF_DUTIES', false),

    // Certificate numbering format. Placeholders: {year}, {seq}.
    'certificate_number_format' => env('TTCS_CERTIFICATE_NUMBER_FORMAT', 'T-{year}-{seq}'),

    // Rate limit (requests per minute) applied to the public verification endpoint.
    'verification_rate_limit' => env('TTCS_VERIFICATION_RATE_LIMIT', 10),
];