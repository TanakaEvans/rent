<?php

namespace App\Services;

use App\Models\ConfigurationAudit;
use App\Models\SystemConfiguration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * Single source of truth for every commercial/billing rule (Module 24).
 *
 * Values are stored denormalised (uncast) in `system_configurations.value` and
 * cast here per the row's `type`. Reads are cache-backed; every change is
 * audited in `configuration_audits` (old/new/by/at/why + approval) and the
 * cache for the touched key is invalidated at write time, so the next engine
 * read sees the new value with zero code changes.
 */
final class ConfigurationService
{
    private const CACHE_PREFIX = 'dzimba.config.';

    public function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever($this->cacheKey($key), function () use ($key) {
            $row = SystemConfiguration::query()
                ->where('status', 'active')
                ->where('key', $key)
                ->first();

            return $row ? $this->cast($row->value, $row->type) : null;
        }) ?? $default;
    }

    /**
     * Typed key => value map for an entire group (engines).
     */
    public function getGroup(string $group): array
    {
        $rows = SystemConfiguration::query()
            ->where('group_name', $group)
            ->where('status', 'active')
            ->get();

        $values = [];
        foreach ($rows as $row) {
            $values[$row->key] = $this->cast($row->value, $row->type);
        }

        return $values;
    }

    /**
     * Change a configuration value. Audits old/new/by/at/why. High/critical
     * risk changes require an approver to be recorded.
     */
    public function set(
        string $key,
        mixed $value,
        ?int $changerId = null,
        ?string $reason = null,
        ?int $approverId = null
    ): SystemConfiguration {
        $row = SystemConfiguration::query()->where('key', $key)->first();

        if (! $row) {
            throw ValidationException::withMessages(['key' => "Unknown configuration key [{$key}]."]);
        }

        if (! $row->is_editable) {
            throw ValidationException::withMessages(['value' => "Configuration key [{$key}] is locked and cannot be edited."]);
        }

        $encoded = self::encode($value, $row->type);

        if (in_array($row->risk, ['high', 'critical'], true) && $approverId === null) {
            throw ValidationException::withMessages(['value' => "Changing [{$key}] is a {$row->risk}-risk change and requires approval."]);
        }

        $old = $row->value;
        $row->value = $encoded;
        $row->save();

        ConfigurationAudit::create([
            'configuration_id' => $row->id,
            'key' => $key,
            'old_value' => $old,
            'new_value' => $encoded,
            'changed_by' => $changerId,
            'reason' => $reason,
            'approved_by' => $approverId,
            'effective_from' => now(),
        ]);

        Cache::forget($this->cacheKey($key));

        return $row;
    }

    /**
     * Restore a key to its originally-seeded default and audit the change.
     */
    public function reset(string $key, ?int $changerId = null, ?string $reason = null, ?int $approverId = null): SystemConfiguration
    {
        $defaults = self::defaults();

        if (! array_key_exists($key, $defaults)) {
            throw ValidationException::withMessages(['key' => "Unknown configuration key [{$key}]."]);
        }

        return $this->set($key, $defaults[$key]['value'], $changerId, $reason, $approverId);
    }

    /**
     * The canonical seeded configuration. Single definition consumed by the
     * ConfigSeeder (initial rows) and reset() (restore-to-default) — the one
     * source of truth for what a "factory default" value is.
     *
     * @return array<string, array{
     *     group: string,
     *     type: string,
     *     value: mixed,
     *     label: string,
     *     description?: string,
     *     risk?: string,
     *     is_editable?: bool
     * }>
     */
    public static function defaults(): array
    {
        return [
            // Subscription lifecycle
            'subscriptions.grace_period_days' => [
                'group' => 'subscriptions',
                'type' => 'integer',
                'value' => 7,
                'label' => 'Grace period (days)',
                'description' => 'Days a subscription stays grace after its cycle ends before suspension.',
                'risk' => 'medium',
            ],
            'subscriptions.proration.mode' => [
                'group' => 'subscriptions',
                'type' => 'string',
                'value' => 'credit_new_invoice',
                'label' => 'Proration mode',
                'description' => 'charge_difference | credit_new_invoice | apply_at_renewal.',
                'risk' => 'high',
            ],
            'subscriptions.suspension.behaviour' => [
                'group' => 'subscriptions',
                'type' => 'string',
                'value' => 'keep_listings',
                'label' => 'Suspension behaviour',
                'description' => 'keep_listings | hide_listings | suspend_premium | full_suspend.',
                'risk' => 'high',
            ],
            'subscriptions.renewal.reminders' => [
                'group' => 'subscriptions',
                'type' => 'json',
                'value' => [30, 14, 7, 3, 1],
                'label' => 'Renewal reminder days',
                'description' => 'Days before expiry to remind the owner.',
                'risk' => 'low',
            ],
            'subscriptions.cycle_days' => [
                'group' => 'subscriptions',
                'type' => 'json',
                'value' => ['monthly' => 30, 'annual' => 365],
                'label' => 'Cycle length (days)',
                'description' => 'Map of billing frequency to its cycle length in days.',
                'risk' => 'medium',
            ],
            'subscriptions.default_plan' => [
                'group' => 'subscriptions',
                'type' => 'string',
                'value' => 'Free',
                'label' => 'Default plan',
                'description' => 'Plan auto-assigned when an owner has no subscription.',
                'risk' => 'medium',
            ],
            'subscriptions.entitlement_over_limit_message' => [
                'group' => 'subscriptions',
                'type' => 'string',
                'value' => "You've reached your plan's listing limit. Upgrade your subscription to publish more properties.",
                'label' => 'Over-limit message',
                'description' => 'Shown when an owner tries to publish past their quota.',
                'risk' => 'low',
            ],

            // Numbering
            'numbering.invoice.prefix' => [
                'group' => 'numbering',
                'type' => 'string',
                'value' => 'SUB',
                'label' => 'Invoice prefix',
                'description' => 'Prefix for settlement invoice numbers.',
                'risk' => 'medium',
            ],
            'numbering.invoice.padding' => [
                'group' => 'numbering',
                'type' => 'integer',
                'value' => 4,
                'label' => 'Invoice sequence padding',
                'description' => 'Zero-padding of the invoice sequence number.',
                'risk' => 'high',
            ],
            'numbering.invoice.start' => [
                'group' => 'numbering',
                'type' => 'integer',
                'value' => 1,
                'label' => 'Invoice sequence start',
                'description' => 'First invoice sequence number of a year.',
                'risk' => 'high',
            ],
            'numbering.invoice.year_reset' => [
                'group' => 'numbering',
                'type' => 'boolean',
                'value' => true,
                'label' => 'Invoice numbers reset yearly',
                'description' => 'When on, sequences restart each calendar year (prefix-year-seq).',
                'risk' => 'low',
            ],
            'numbering.receipt.prefix' => [
                'group' => 'numbering',
                'type' => 'string',
                'value' => 'RCT',
                'label' => 'Receipt prefix',
                'description' => 'Prefix for settlement receipt numbers.',
                'risk' => 'medium',
            ],
            'numbering.receipt.length' => [
                'group' => 'numbering',
                'type' => 'integer',
                'value' => 8,
                'label' => 'Receipt suffix length',
                'description' => 'Length of the random suffix after the receipt prefix.',
                'risk' => 'low',
            ],
            'numbering.rent_invoice.prefix' => [
                'group' => 'numbering',
                'type' => 'string',
                'value' => 'RNT',
                'label' => 'Rent invoice prefix',
                'description' => 'Prefix for rent invoice numbers (shares padding/start/year-reset with the invoice group).',
                'risk' => 'medium',
            ],

            // Rent invoice lifecycle
            'invoices.timing' => [
                'group' => 'invoices',
                'type' => 'string',
                'value' => 'billing_date',
                'label' => 'Rent invoice timing',
                'description' => 'When a rent invoice becomes due: immediate | billing_date.',
                'risk' => 'medium',
            ],
            'invoices.reminder_lead_days' => [
                'group' => 'invoices',
                'type' => 'integer',
                'value' => 3,
                'label' => 'Due reminder lead (days)',
                'description' => 'Days before an invoice is due that the tenant gets a reminder.',
                'risk' => 'low',
            ],
            'invoices.overdue_days' => [
                'group' => 'invoices',
                'type' => 'integer',
                'value' => 0,
                'label' => 'Overdue grace (days)',
                'description' => 'Days after a billing cycle ends before an unpaid invoice is marked overdue.',
                'risk' => 'medium',
            ],

            // Featured & advertising
            'featured.enabled' => [
                'group' => 'featured',
                'type' => 'boolean',
                'value' => true,
                'label' => 'Advertising enabled',
                'description' => 'Master switch for the featured & advertising module. When off, no new placements can be booked.',
                'risk' => 'high',
            ],
            'featured.approval_required' => [
                'group' => 'featured',
                'type' => 'boolean',
                'value' => true,
                'label' => 'Staff approval required',
                'description' => 'When on, every placement sits reserved until staff approves (payment/approval gateway). Off means a booking activates its window immediately.',
                'risk' => 'medium',
            ],
            'featured.default_price' => [
                'group' => 'featured',
                'type' => 'decimal',
                'value' => 0.00,
                'label' => 'Default placement price',
                'description' => 'Price for a single featured placement when packages have none.',
                'risk' => 'high',
            ],
            'featured.max_per_property' => [
                'group' => 'featured',
                'type' => 'integer',
                'value' => 1,
                'label' => 'Max placements per property',
                'description' => 'How many active placements one property may hold.',
                'risk' => 'medium',
            ],
            'featured.auto_expiry_days' => [
                'group' => 'featured',
                'type' => 'integer',
                'value' => 30,
                'label' => 'Placement expiry (days)',
                'description' => 'Default window length for a placement.',
                'risk' => 'medium',
            ],
            'featured.max_active_per_owner' => [
                'group' => 'featured',
                'type' => 'integer',
                'value' => 3,
                'label' => 'Max active placements per owner',
                'description' => 'Spend cap — how many open placements one owner may hold at a time (budget control).',
                'risk' => 'medium',
            ],

            // Maintenance report & triage (Module 10)
            'maintenance.request_no.padding' => [
                'group' => 'maintenance',
                'type' => 'integer',
                'value' => 5,
                'label' => 'Request number padding',
                'description' => 'Zero-padding of the MR sequence number.',
                'risk' => 'low',
            ],
            'maintenance.categories' => [
                'group' => 'maintenance',
                'type' => 'json',
                'value' => ['plumbing', 'electrical', 'appliance', 'structural', 'pest', 'safety', 'other'],
                'label' => 'Report categories',
                'description' => 'Fault categories a tenant may choose when reporting (plumbing | electrical | appliance | structural | pest | safety | other).',
                'risk' => 'low',
            ],
            'maintenance.escalation_enabled' => [
                'group' => 'maintenance',
                'type' => 'boolean',
                'value' => true,
                'label' => 'SLA escalation enabled',
                'description' => 'When on, the daily sweep escalates first-response breaches to staff.',
                'risk' => 'medium',
            ],
            'maintenance.sla.low_hours' => [
                'group' => 'maintenance',
                'type' => 'integer',
                'value' => 168,
                'label' => 'Low priority SLA (hours)',
                'description' => 'First-response SLA window for low-priority requests.',
                'risk' => 'medium',
            ],
            'maintenance.sla.medium_hours' => [
                'group' => 'maintenance',
                'type' => 'integer',
                'value' => 96,
                'label' => 'Medium priority SLA (hours)',
                'description' => 'First-response SLA window for medium-priority requests.',
                'risk' => 'medium',
            ],
            'maintenance.sla.high_hours' => [
                'group' => 'maintenance',
                'type' => 'integer',
                'value' => 48,
                'label' => 'High priority SLA (hours)',
                'description' => 'First-response SLA window for high-priority requests.',
                'risk' => 'medium',
            ],
            'maintenance.sla.emergency_hours' => [
                'group' => 'maintenance',
                'type' => 'integer',
                'value' => 24,
                'label' => 'Emergency priority SLA (hours)',
                'description' => 'First-response SLA window for emergency requests (NFR-01: 24h).',
                'risk' => 'high',
            ],

            // Rent & payments
            'payments.methods' => [
                'group' => 'payments',
                'type' => 'json',
                'value' => ['cash', 'bank', 'mobile', 'online'],
                'label' => 'Enabled payment methods',
                'description' => 'Payment methods tenants may use (cash | bank | mobile | online).',
                'risk' => 'medium',
            ],
            'payments.approval.threshold' => [
                'group' => 'payments',
                'type' => 'decimal',
                'value' => 500.00,
                'label' => 'Payment approval threshold',
                'description' => 'Payments/POPs above this amount require staff approval.',
                'risk' => 'critical',
            ],
            'payments.pop.approval_required' => [
                'group' => 'payments',
                'type' => 'boolean',
                'value' => true,
                'label' => 'Proof-of-payment approval required',
                'description' => 'Whether POP uploads must be approved before a payment is settled.',
                'risk' => 'medium',
            ],
            'late_fees.enabled' => [
                'group' => 'late_fees',
                'type' => 'boolean',
                'value' => false,
                'label' => 'Late fees enabled',
                'description' => 'Whether overdue invoices accrue late fees.',
                'risk' => 'medium',
            ],
            'late_fees.type' => [
                'group' => 'late_fees',
                'type' => 'string',
                'value' => 'percent',
                'label' => 'Late-fee type',
                'description' => 'How the charge is calculated per applied period: percent (of the invoice amount) or fixed (flat amount).',
                'risk' => 'high',
            ],
            'late_fees.value' => [
                'group' => 'late_fees',
                'type' => 'decimal',
                'value' => 1.00,
                'label' => 'Late-fee value',
                'description' => 'The charge per applied period: percentage of the invoice amount when type is percent, else a flat amount.',
                'risk' => 'high',
            ],
            'late_fees.cap' => [
                'group' => 'late_fees',
                'type' => 'decimal',
                'value' => 0.00,
                'label' => 'Late-fee cap',
                'description' => 'The maximum total late fee per invoice. 0.00 means no cap.',
                'risk' => 'high',
            ],
            'late_fees.period_days' => [
                'group' => 'late_fees',
                'type' => 'integer',
                'value' => 30,
                'label' => 'Late-fee applied period (days)',
                'description' => 'How often the late-fee charge is applied while an invoice stays overdue.',
                'risk' => 'medium',
            ],

            // Marketplace presentation (Property Marketplace doc)
            'marketplace.listings_per_page' => [
                'group' => 'marketplace',
                'type' => 'integer',
                'value' => 12,
                'label' => 'Listings per page',
                'description' => 'Property cards shown per page in the marketplace.',
                'risk' => 'low',
            ],
            'marketplace.default_view' => [
                'group' => 'marketplace',
                'type' => 'string',
                'value' => 'grid',
                'label' => 'Default result view',
                'description' => 'grid | list | map.',
                'risk' => 'low',
            ],
            'marketplace.default_sort' => [
                'group' => 'marketplace',
                'type' => 'string',
                'value' => 'newest',
                'label' => 'Default sort',
                'description' => 'newest | recently_updated | price_asc | price_desc | featured | price_per_m2.',
                'risk' => 'low',
            ],
            'marketplace.quick_view_enabled' => [
                'group' => 'marketplace',
                'type' => 'boolean',
                'value' => true,
                'label' => 'Quick view enabled',
                'description' => 'Show the Quick View side panel for property cards.',
                'risk' => 'low',
            ],
            'marketplace.compare_enabled' => [
                'group' => 'marketplace',
                'type' => 'boolean',
                'value' => true,
                'label' => 'Compare enabled',
                'description' => 'Allow tenants to compare selected properties.',
                'risk' => 'low',
            ],
            'marketplace.compare_max' => [
                'group' => 'marketplace',
                'type' => 'integer',
                'value' => 3,
                'label' => 'Max compare properties',
                'description' => 'How many properties a tenant may compare at once.',
                'risk' => 'low',
            ],
            'marketplace.map_enabled' => [
                'group' => 'marketplace',
                'type' => 'boolean',
                'value' => true,
                'label' => 'Map view enabled',
                'description' => 'Show the Grid | List | Map toggle on the marketplace.',
                'risk' => 'low',
            ],
            'marketplace.map_default_center' => [
                'group' => 'marketplace',
                'type' => 'json',
                'value' => ['lat' => -17.8292, 'lng' => 31.0522, 'zoom' => 11],
                'label' => 'Map default centre',
                'description' => 'Default map centre/zoom (lat, lng, zoom).',
                'risk' => 'low',
            ],
            'marketplace.featured_position' => [
                'group' => 'marketplace',
                'type' => 'string',
                'value' => 'top',
                'label' => 'Featured position',
                'description' => 'Where featured listings appear: top | carousel | mixed.',
                'risk' => 'low',
            ],
            'marketplace.popular_threshold' => [
                'group' => 'marketplace',
                'type' => 'json',
                'value' => ['views' => 60, 'saves' => 2],
                'label' => 'Popular badge threshold',
                'description' => 'Views/saves a property needs to earn the Popular badge.',
                'risk' => 'low',
            ],
            'marketplace.recommendations_enabled' => [
                'group' => 'marketplace',
                'type' => 'boolean',
                'value' => true,
                'label' => 'Recommendations enabled',
                'description' => 'Show personalized "Recommended for you" for logged-in tenants.',
                'risk' => 'low',
            ],
            'marketplace.recommendations_limit' => [
                'group' => 'marketplace',
                'type' => 'integer',
                'value' => 4,
                'label' => 'Recommendation limit',
                'description' => 'How many properties appear in "Recommended for you".',
                'risk' => 'low',
            ],
            'marketplace.recently_viewed_limit' => [
                'group' => 'marketplace',
                'type' => 'integer',
                'value' => 6,
                'label' => 'Recently viewed limit',
                'description' => 'How many recently viewed properties to show.',
                'risk' => 'low',
            ],
            'marketplace.suggestions_limit' => [
                'group' => 'marketplace',
                'type' => 'integer',
                'value' => 6,
                'label' => 'Search suggestions limit',
                'description' => 'Location suggestions returned as the tenant types.',
                'risk' => 'low',
            ],
            'marketplace.amenities' => [
                'group' => 'marketplace',
                'type' => 'json',
                'value' => [
                    'borehole' => 'Borehole', 'solar' => 'Solar', 'backup_power' => 'Backup power',
                    'water_tank' => 'Water tank', 'internet' => 'Internet', 'garden' => 'Garden',
                    'pool' => 'Pool', 'swimming_pool' => 'Swimming pool', 'security' => 'Security',
                    'gated_community' => 'Gated community', 'carport' => 'Carport', 'parking' => 'Parking',
                    'secure_parking' => 'Secure parking', 'double_garage' => 'Double garage',
                    'automated_gate' => 'Automated gate', 'aircon' => 'Air conditioning',
                    'air_conditioning' => 'Air conditioning', 'built_in_cupboards' => 'Built-in cupboards',
                    'ensuite' => 'Ensuite', 'balcony' => 'Balcony', 'pet_friendly' => 'Pet friendly',
                    'generator' => 'Generator', 'prepaid_utilities' => 'Prepaid utilities',
                    'servants_quarters' => "Servant's quarters",
                ],
                'label' => 'Marketplace amenity filters',
                'description' => 'Amenities shown in the More Filters panel (key => label).',
                'risk' => 'low',
            ],
            'marketplace.badges' => [
                'group' => 'marketplace',
                'type' => 'json',
                'value' => [
                    'verified' => true, 'verified_owner' => true, 'featured' => true,
                    'new' => true, 'popular' => true, 'quality' => true,
                ],
                'label' => 'Card badges',
                'description' => 'Which trust badges render on cards (verified, verified_owner, featured, new, popular, quality).',
                'risk' => 'low',
            ],
            'marketplace.report_categories' => [
                'group' => 'marketplace',
                'type' => 'json',
                'value' => ['suspicious_listing', 'incorrect_information', 'duplicate', 'wrong_price', 'fraud_concern', 'already_rented', 'inappropriate_content'],
                'label' => 'Report categories',
                'description' => 'Reasons a visitor can report a listing.',
                'risk' => 'low',
            ],

            // Listing lifecycle (Marketplace §40/§41)
            'listings.validity_days' => [
                'group' => 'listings',
                'type' => 'integer',
                'value' => 60,
                'label' => 'Listing validity (days)',
                'description' => 'How long a published listing stays online before expiry.',
                'risk' => 'medium',
            ],
            'listings.validity_reminders' => [
                'group' => 'listings',
                'type' => 'json',
                'value' => [14, 7, 1],
                'label' => 'Expiry reminder days',
                'description' => 'Days before expiry the owner is reminded to renew.',
                'risk' => 'low',
            ],
            'listings.auto_expire' => [
                'group' => 'listings',
                'type' => 'boolean',
                'value' => true,
                'label' => 'Auto-expire listings',
                'description' => 'When on, the daily scheduler expires listings past their validity window.',
                'risk' => 'medium',
            ],
            'listings.renew_grace_days' => [
                'group' => 'listings',
                'type' => 'integer',
                'value' => 7,
                'label' => 'Renew grace (days)',
                'description' => 'Days an owner may renew an expired listing before it becomes unavailable.',
                'risk' => 'low',
            ],
        ];
    }

    private function cacheKey(string $key): string
    {
        return self::CACHE_PREFIX . $key;
    }

    private function cast(?string $value, string $type): mixed
    {
        return match ($type) {
            'integer' => (int) $value,
            'boolean' => in_array((string) $value, ['1', 'true', 'yes', 'on'], true),
            'decimal' => (string) $value,
            'json' => json_decode((string) $value, true),
            default => (string) $value,
        };
    }

    /**
     * Serialise a value for storage per its column type.
     */
    public static function encode(mixed $value, string $type): string
    {
        switch ($type) {
            case 'integer':
                if (! is_numeric($value)) {
                    throw ValidationException::withMessages(['value' => 'Expected an integer value.']);
                }

                return (string) (int) $value;

            case 'boolean':
                $truthy = ['1', 'true', 'yes', 'on', 1, true];
                $falsy = ['0', 'false', 'no', 'off', 0, false];

                if (in_array($value, $truthy, true)) {
                    return '1';
                }

                if (in_array($value, $falsy, true)) {
                    return '0';
                }

                throw ValidationException::withMessages(['value' => 'Expected a boolean value.']);

            case 'decimal':
                if (! is_numeric($value)) {
                    throw ValidationException::withMessages(['value' => 'Expected a decimal value.']);
                }

                return number_format((float) $value, 2, '.', '');

            case 'json':
                if (! is_array($value)) {
                    throw ValidationException::withMessages(['value' => 'Expected a JSON value.']);
                }

                return json_encode($value);

            default:
                return (string) $value;
        }
    }
}