<?php

namespace Database\Seeders;

use App\Models\SubscriptionFeature;
use App\Models\SubscriptionPlan;
use App\Models\SystemConfiguration;
use App\Services\ConfigurationService;
use Illuminate\Database\Seeder;

class ConfigSeeder extends Seeder
{
    /**
     * Seed the Configuration Centre (Module 24) defaults and the Feature
     * Catalogue with per-plan grants. Baseline values are written without
     * audit rows (they ARE the baseline); changes flow through
     * ConfigurationService and are audited from then on.
     */
    public function run(): void
    {
        foreach (ConfigurationService::defaults() as $key => $entry) {
            SystemConfiguration::updateOrCreate(
                ['key' => $key],
                [
                    'group_name' => $entry['group'],
                    'type' => $entry['type'],
                    'value' => ConfigurationService::encode($entry['value'], $entry['type']),
                    'label' => $entry['label'],
                    'description' => $entry['description'] ?? null,
                    'risk' => $entry['risk'] ?? 'low',
                    'is_editable' => $entry['is_editable'] ?? true,
                    'status' => 'active',
                ]
            );
        }

        $features = [
            ['code' => 'PROPERTY_LISTING', 'label' => 'Property listings', 'description' => 'List and manage properties.'],
            ['code' => 'PROPERTY_ANALYTICS', 'label' => 'Listing analytics', 'description' => 'Views, enquiries and applications per listing.'],
            ['code' => 'ADVANCED_SEARCH', 'label' => 'Advanced search', 'description' => 'Saved searches and refined marketplace filters.'],
            ['code' => 'TENANT_MESSAGING', 'label' => 'Tenant messaging', 'description' => 'Enquiries and conversations with tenants.'],
            ['code' => 'VIEWING_MANAGEMENT', 'label' => 'Viewing management', 'description' => 'Slots and viewing requests.'],
            ['code' => 'APPLICATION_MANAGEMENT', 'label' => 'Application management', 'description' => 'Review and decide rental applications.'],
            ['code' => 'RENT_COLLECTION', 'label' => 'Rent collection', 'description' => 'Rent schedules, invoices and receipts.'],
            ['code' => 'MAINTENANCE', 'label' => 'Maintenance module', 'description' => 'Track repairs and assign contractors.'],
            ['code' => 'FINANCIAL_REPORTS', 'label' => 'Financial reports', 'description' => 'Income and occupancy reporting.'],
            ['code' => 'FEATURED_LISTINGS', 'label' => 'Featured listings', 'description' => 'Promote listings above organic results.'],
            ['code' => 'MULTIPLE_USERS', 'label' => 'Multiple users', 'description' => 'Invite team members to one account.'],
            ['code' => 'MULTIPLE_BRANCHES', 'label' => 'Multiple branches', 'description' => 'Organise properties across branches.'],
            ['code' => 'API_ACCESS', 'label' => 'API access', 'description' => 'Programmatic access to owner data.'],
            ['code' => 'DEDICATED_SUPPORT', 'label' => 'Dedicated support', 'description' => 'Priority support channel.'],
        ];

        foreach ($features as $feature) {
            SubscriptionFeature::updateOrCreate(
                ['code' => $feature['code']],
                $feature + ['status' => 'active']
            );
        }

        $grants = [
            'Free' => ['PROPERTY_LISTING', 'TENANT_MESSAGING', 'VIEWING_MANAGEMENT', 'APPLICATION_MANAGEMENT'],
            'Basic' => ['PROPERTY_LISTING', 'TENANT_MESSAGING', 'VIEWING_MANAGEMENT', 'APPLICATION_MANAGEMENT', 'FEATURED_LISTINGS'],
            'Professional' => ['PROPERTY_LISTING', 'TENANT_MESSAGING', 'VIEWING_MANAGEMENT', 'APPLICATION_MANAGEMENT', 'FEATURED_LISTINGS', 'PROPERTY_ANALYTICS', 'FINANCIAL_REPORTS', 'ADVANCED_SEARCH'],
            'Business' => ['PROPERTY_LISTING', 'TENANT_MESSAGING', 'VIEWING_MANAGEMENT', 'APPLICATION_MANAGEMENT', 'FEATURED_LISTINGS', 'PROPERTY_ANALYTICS', 'FINANCIAL_REPORTS', 'ADVANCED_SEARCH', 'RENT_COLLECTION', 'MAINTENANCE', 'MULTIPLE_USERS', 'DEDICATED_SUPPORT'],
        ];

        foreach ($grants as $planName => $codes) {
            $plan = SubscriptionPlan::where('name', $planName)->first();
            if (! $plan) {
                continue;
            }

            $featureIds = SubscriptionFeature::whereIn('code', $codes)->pluck('id')->all();
            $plan->features()->sync($featureIds);
        }
    }
}