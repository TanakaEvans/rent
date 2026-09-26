<?php

/*
 * Admin guide (shown only inside the admin portal). Every article cites the
 * feature tests that prove it (verified_by) — see tests/Feature/UserManualTest.
 */

return [
    'key' => 'admin',
    'title' => 'Admin guide',
    'tagline' => 'How to run ZimRent day to day: people and access, trust and safety, payments, operations and platform settings.',
    'role' => 'Admin',
    'sections' => [
        [
            'id' => 'getting-started',
            'title' => 'Getting started',
            'articles' => [
                [
                    'id' => 'access',
                    'title' => 'Who can use the admin portal',
                    'summary' => 'Users with the **Admin** or **Superuser** role can open the admin portal. Everyone else is refused, even if they know the address.',
                    'steps' => [
                        'Sign in with your email address or username. Admins land on the **Admin Dashboard**.',
                        'If your password is new or has expired you must change it first. New passwords need at least 8 characters with upper and lower case letters, a number and a symbol.',
                        'Use the left-hand menu to move between areas. **Admin Guide** (this page) is at the bottom.',
                    ],
                    'notes' => [
                        'After 7 wrong passwords in a row an account is locked. Another admin can unlock it from **Auth Management**.',
                    ],
                    'links' => [
                        ['label' => 'Open the admin dashboard', 'route' => 'admin.dashboard'],
                    ],
                    'verified_by' => [
                        'Tests\Feature\DzimbaAccessControlTest::test_admin_can_access_admin_dashboard',
                        'Tests\Feature\DzimbaAccessControlTest::test_tenant_cannot_access_auth_management',
                        'Tests\Feature\UserManualTest::test_admin_guide_opens_inside_the_admin_portal_for_admins_only',
                    ],
                ],
                [
                    'id' => 'dashboard',
                    'title' => 'Read the dashboard',
                    'summary' => 'The dashboard shows marketplace health (total properties, live listings, verified properties, applications) and platform operations (users, roles, employees, branches, departments).',
                    'links' => [
                        ['label' => 'Open the admin dashboard', 'route' => 'admin.dashboard'],
                        ['label' => 'Open marketplace analytics', 'route' => 'admin.marketplace.analytics'],
                    ],
                    'verified_by' => [
                        'Tests\Feature\DzimbaAccessControlTest::test_admin_can_access_admin_dashboard',
                        'Tests\Feature\TrustGovernTest::test_admin_marketplace_analytics_renders_platform_totals',
                    ],
                ],
            ],
        ],
        [
            'id' => 'people',
            'title' => 'People and access',
            'articles' => [
                [
                    'id' => 'make-owner',
                    'title' => 'Give someone an owner account',
                    'summary' => 'Anyone who signs up publicly becomes a tenant. To let them list property, add the **Owner** role to their account.',
                    'steps' => [
                        'Open **System Users** and find the person (search by name, email or username).',
                        'Select **Edit**, tick the **Owner** role and save.',
                        'The next time they sign in they land on the Owner Dashboard. They keep their tenant features too.',
                    ],
                    'notes' => [
                        'To add or remove a role for many people at once, use **Bulk Assign Roles** or **Bulk Remove Roles**.',
                    ],
                    'links' => [
                        ['label' => 'Open system users', 'route' => 'auth.users.index'],
                        ['label' => 'Bulk assign roles', 'route' => 'auth.roles.bulk-assign'],
                    ],
                    'verified_by' => [
                        'Tests\Feature\AdminManualTest::test_admin_can_make_an_existing_user_an_owner',
                        'Tests\Feature\SignupTest::test_public_signup_creates_a_tenant_with_default_role',
                    ],
                ],
                [
                    'id' => 'deactivate',
                    'title' => 'Deactivate or reactivate a user',
                    'summary' => 'Deactivated users cannot sign in, and are signed out if they are already signed in.',
                    'steps' => [
                        'In **System Users**, use **Deactivate** on the user’s row (or **Activate** to restore access).',
                    ],
                    'notes' => [
                        'You cannot deactivate yourself or remove your own admin role.',
                        'Users who own properties or have leases or payments cannot be deleted — deactivate them instead so their records stay intact.',
                    ],
                    'links' => [
                        ['label' => 'Open system users', 'route' => 'auth.users.index'],
                    ],
                    'verified_by' => [
                        'Tests\Feature\AdminManualTest::test_admin_can_make_an_existing_user_an_owner',
                    ],
                ],
                [
                    'id' => 'passwords',
                    'title' => 'Reset a password or unlock an account',
                    'steps' => [
                        'Open **Auth Management** and search for the user.',
                        'Use **Unlock** for an account locked after too many wrong passwords.',
                        'Use **Reset Pass** to issue a temporary password. Give it to the user privately; they must choose a new password when they next sign in.',
                    ],
                    'links' => [
                        ['label' => 'Open auth management', 'route' => 'auth.management'],
                    ],
                    'verified_by' => [
                        'Tests\Feature\DzimbaAccessControlTest::test_admin_can_access_auth_management',
                    ],
                ],
                [
                    'id' => 'roles',
                    'title' => 'Manage roles',
                    'summary' => 'Roles decide which part of ZimRent a person sees: **Owner**, **Tenant**, **Contractor**, **Admin** and **Superuser**.',
                    'steps' => [
                        'Open **User Roles** to see every role and how many users hold it.',
                        'Open **Users with Roles** for a report of who holds which roles.',
                    ],
                    'notes' => [
                        'The built-in roles cannot be renamed or deleted because the system relies on their names. A role that still has users cannot be deleted.',
                    ],
                    'links' => [
                        ['label' => 'Open user roles', 'route' => 'auth.roles.index'],
                        ['label' => 'Open users with roles', 'route' => 'auth.roles.users-report'],
                    ],
                    'verified_by' => [
                        'Tests\Feature\DzimbaAccessControlTest::test_owner_cannot_access_system_users',
                    ],
                ],
                [
                    'id' => 'employees',
                    'title' => 'Add staff (employees)',
                    'steps' => [
                        'Open **Employees** and add a new employee.',
                        'Tick **Create System User Account** to give them a login, and choose their roles. An email address is required for a login.',
                        'Their first password is their surname in lower case; they must change it when they first sign in.',
                    ],
                    'links' => [
                        ['label' => 'Open employees', 'route' => 'admin.employees.index'],
                    ],
                    'verified_by' => [
                        'Tests\Feature\DzimbaAccessControlTest::test_admin_can_access_admin_dashboard',
                    ],
                ],
            ],
        ],
        [
            'id' => 'trust',
            'title' => 'Trust and safety',
            'articles' => [
                [
                    'id' => 'kyc',
                    'title' => 'Review identity documents (KYC)',
                    'summary' => 'Tenants upload a national ID and/or driving licence. Approving evidence earns them a badge that owners can see.',
                    'steps' => [
                        'Open **KYC Review**. Filter by pending, approved or rejected.',
                        'Open the scan with **View**, then **Approve** or **Reject** it. Add a note when rejecting — the tenant receives it.',
                        'You can **Revoke** an approved document later if needed.',
                    ],
                    'notes' => [
                        'One approved document gives a **Silver** badge; both give **Gold**. Every decision notifies the tenant and is recorded in an audit trail.',
                    ],
                    'links' => [
                        ['label' => 'Open KYC review', 'route' => 'admin.kyc.index'],
                    ],
                    'verified_by' => [
                        'Tests\Feature\KycAdminTest::test_admin_sees_the_review_queue_with_tiers_counts_and_filters',
                        'Tests\Feature\KycAdminTest::test_approving_evidence_earns_silver_then_gold_and_notifies',
                        'Tests\Feature\KycAdminTest::test_rejecting_with_a_note_keeps_the_trail_and_sends_the_reason',
                        'Tests\Feature\KycAdminTest::test_revoking_an_approved_document_drops_the_badge',
                    ],
                ],
                [
                    'id' => 'reports',
                    'title' => 'Moderate reported listings',
                    'summary' => 'Anyone can report a listing. Reports arrive in the **Marketplace Reports** moderation queue.',
                    'steps' => [
                        'Open **Marketplace Reports** and filter by status or priority.',
                        'Move a report through review: under review, escalated, resolved or dismissed.',
                        'When resolving a report about a property, you can also take the listing down — it becomes unavailable and the change is recorded in the property history.',
                    ],
                    'notes' => ['A resolved report is final. A dismissed report can be re-opened.'],
                    'links' => [
                        ['label' => 'Open the moderation queue', 'route' => 'admin.marketplace.reports.index'],
                    ],
                    'verified_by' => [
                        'Tests\Feature\TrustGovernTest::test_admin_can_resolve_a_report_with_a_note',
                        'Tests\Feature\TrustGovernTest::test_reports_follow_an_explicit_state_machine',
                        'Tests\Feature\TrustGovernTest::test_resolving_a_reported_property_can_take_the_listing_down',
                    ],
                ],
            ],
        ],
        [
            'id' => 'money',
            'title' => 'Payments, plans and promotions',
            'articles' => [
                [
                    'id' => 'payments',
                    'title' => 'Approve rent payments',
                    'summary' => 'Some tenant payments wait for a staff check before the invoice counts as paid: large payments (at or above the approval threshold) and bank or mobile payments that come with proof of payment.',
                    'steps' => [
                        'Open **Payment Approvals** to see payments awaiting confirmation.',
                        'Check the method, reference and proof of payment.',
                        '**Approve** to settle the invoice and issue a receipt, or **Reject** to leave the invoice owing so the tenant can pay again.',
                    ],
                    'links' => [
                        ['label' => 'Open payment approvals', 'route' => 'admin.rent.payments.index'],
                    ],
                    'verified_by' => [
                        'Tests\Feature\MonetiseTest::test_high_value_payment_waits_for_staff_then_approval_settles_it',
                        'Tests\Feature\MonetiseTest::test_rejected_payment_leaves_invoice_owing_and_allows_re_payment',
                        'Tests\Feature\MonetiseTest::test_bank_payment_requires_proof_of_payment_when_configured',
                        'Tests\Feature\DzimbaAccessControlTest::test_admin_can_access_payment_approvals',
                    ],
                ],
                [
                    'id' => 'plans',
                    'title' => 'Manage subscription plans',
                    'summary' => 'Plans set how many listings an owner can have available at once.',
                    'steps' => [
                        'Open **Subscription Plans** and create or edit a plan: name, price, billing cycle and listing limit (leave blank for unlimited).',
                        'Archive plans you no longer offer. A plan that owners are using is archived rather than deleted, so their subscriptions keep working.',
                    ],
                    'links' => [
                        ['label' => 'Open subscription plans', 'route' => 'admin.subscriptions.plans.index'],
                    ],
                    'verified_by' => [
                        'Tests\Feature\MonetiseTest::test_admin_can_create_update_and_remove_plans',
                        'Tests\Feature\MonetiseTest::test_plan_in_use_is_archived_not_deleted',
                        'Tests\Feature\MonetiseTest::test_archived_plan_cannot_be_selected',
                    ],
                ],
                [
                    'id' => 'ads',
                    'title' => 'Approve listing promotions',
                    'summary' => 'Owners book promotions for their listings. When approval is required, bookings wait in the **Ad Placements** approval queue.',
                    'steps' => [
                        'Open **Ad Placements**. **Approve** a reserved booking to start its promotion window — the listing becomes featured and the owner is notified.',
                        'Pause, resume or cancel live promotions. Cancelling credits the owner for the unused part.',
                    ],
                    'links' => [
                        ['label' => 'Open ad placements', 'route' => 'admin.advertising.index'],
                    ],
                    'verified_by' => [
                        'Tests\Feature\AdsTest::test_admin_can_approve_a_reserved_order',
                        'Tests\Feature\AdsTest::test_admin_can_cancel_a_live_placement_with_a_note',
                        'Tests\Feature\AdsTest::test_paused_placements_are_frozen_and_resuming_extends_the_window',
                        'Tests\Feature\AdsTest::test_cancelling_an_active_window_prorates_the_unused_portion',
                    ],
                ],
            ],
        ],
        [
            'id' => 'operations',
            'title' => 'Maintenance and contractors',
            'articles' => [
                [
                    'id' => 'escalations',
                    'title' => 'Handle maintenance escalations',
                    'summary' => 'Every repair request has a first-response deadline based on its priority. Requests nobody has acted on by the deadline are escalated to staff, and admins are notified.',
                    'steps' => [
                        'Open **Maintenance Escalations** to see breached requests.',
                        'Select **Take ownership** to acknowledge a request, then follow up with the owner.',
                    ],
                    'links' => [
                        ['label' => 'Open maintenance escalations', 'route' => 'admin.maintenance.escalations.index'],
                    ],
                    'verified_by' => [
                        'Tests\Feature\OperateTest::test_escalate_command_breaches_past_sla_requests_once_and_pages_staff',
                        'Tests\Feature\OperateTest::test_admin_queue_lists_sla_breaches_and_ack_clears_it',
                        'Tests\Feature\OperateTest::test_emergency_report_notifies_owner_and_staff_immediately',
                    ],
                ],
                [
                    'id' => 'contractors',
                    'title' => 'Register and vet contractors',
                    'summary' => 'Only verified contractors can be assigned repair jobs by owners.',
                    'steps' => [
                        'Open **Contractor Registry** and register a tradesperson with their trades and rates. New contractors start in vetting.',
                        'Once checked, move them to **verified**. Suspend a contractor to stop new assignments.',
                    ],
                    'links' => [
                        ['label' => 'Open contractor registry', 'route' => 'admin.contractors.index'],
                    ],
                    'verified_by' => [
                        'Tests\Feature\OperateTest::test_admin_registers_contractor_into_vetting_then_verifies',
                        'Tests\Feature\OperateTest::test_registry_transitions_follow_the_state_machine',
                        'Tests\Feature\OperateTest::test_suspended_contractors_receive_no_new_assignments',
                    ],
                ],
            ],
        ],
        [
            'id' => 'settings',
            'title' => 'Platform settings',
            'articles' => [
                [
                    'id' => 'configuration',
                    'title' => 'Change business rules (Configuration Centre)',
                    'summary' => 'Rules such as grace periods, approval thresholds, late fees, listing validity and numbering are settings, not code.',
                    'steps' => [
                        'Open **Configuration Centre** and change the values you need.',
                        'Write a reason for the change — it is stored in the audit trail with your name.',
                        'Save. Recent changes are listed at the bottom of the page.',
                    ],
                    'notes' => ['Some critical settings are locked and cannot be changed from the screen.'],
                    'links' => [
                        ['label' => 'Open configuration centre', 'route' => 'admin.configuration.index'],
                    ],
                    'verified_by' => [
                        'Tests\Feature\ConfigurationTest::test_admin_configuration_centre_updates_rule_values',
                        'Tests\Feature\ConfigurationTest::test_config_change_is_audited_and_cache_is_invalidated',
                        'Tests\Feature\ConfigurationTest::test_admin_cannot_set_unknown_or_locked_config_key',
                    ],
                ],
                [
                    'id' => 'organisation',
                    'title' => 'Company details and structure',
                    'steps' => [
                        'Open **Company Details** to set the company name and upload the logo (save the details before uploading a logo).',
                        'Use **Branches**, **Departments** and **Sections** to mirror how your team is organised. Records that still have employees cannot be deleted.',
                    ],
                    'links' => [
                        ['label' => 'Open company details', 'route' => 'admin.company.index'],
                        ['label' => 'Open branches', 'route' => 'admin.branches.index'],
                        ['label' => 'Open departments', 'route' => 'admin.departments.index'],
                        ['label' => 'Open sections', 'route' => 'admin.sections.index'],
                    ],
                    'verified_by' => [
                        'Tests\Feature\DzimbaAccessControlTest::test_admin_can_access_admin_dashboard',
                    ],
                ],
            ],
        ],
    ],
];
