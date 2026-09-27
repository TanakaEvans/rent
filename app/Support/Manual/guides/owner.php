<?php

/*
 * Owner guide. Every article cites the feature tests that prove it
 * (verified_by) — see tests/Feature/UserManualTest.
 */

return [
    'key' => 'owner',
    'title' => 'Owner guide',
    'tagline' => 'List your property, meet tenants directly, sign leases and track your rent — without paying agent commission.',
    'role' => 'Owner',
    'sections' => [
        [
            'id' => 'getting-started',
            'title' => 'Getting started',
            'articles' => [
                [
                    'id' => 'owner-account',
                    'title' => 'Create an owner account',
                    'summary' => 'Sign up as an owner in under a minute — no waiting for the ZimRent team.',
                    'steps' => [
                        'Choose **List your property** anywhere on the marketplace, or open **Create account** and pick the **List property** tab.',
                        'Enter your name, email and a password, accept the terms and submit. You are signed in straight away on the **Owner Dashboard**.',
                        'Add your first property from **My Properties** — listings go live once they pass verification.',
                    ],
                    'notes' => [
                        'Passwords must be at least 8 characters and include upper and lower case letters, a number and a symbol.',
                        'Already signed up as a tenant? Ask the ZimRent team to add the Owner role to your existing account.',
                    ],
                    'links' => [
                        ['label' => 'Open my dashboard', 'route' => 'owner.dashboard'],
                    ],
                    'verified_by' => [
                        'Tests\Feature\SignupTest::test_owner_signup_creates_an_owner_and_lands_on_the_owner_dashboard',
                        'Tests\Feature\SignupTest::test_signed_up_owner_can_reach_the_owner_dashboard_but_not_admin',
                        'Tests\Feature\SignupTest::test_register_page_preselects_owner_when_asked',
                        'Tests\Feature\DzimbaAccessControlTest::test_owner_can_access_owner_dashboard',
                        'Tests\Feature\AdminManualTest::test_admin_can_make_an_existing_user_an_owner',
                    ],
                ],
                [
                    'id' => 'reset-password',
                    'title' => 'Reset a forgotten password',
                    'summary' => 'Locked out? Reset your own password by email — no need to contact anyone.',
                    'steps' => [
                        'On the sign-in page choose **Forgot your password?**',
                        'Enter your account email and submit. If it is registered, we email you a reset link.',
                        'Open the link and set a new password — you can then sign in with it.',
                    ],
                    'notes' => [
                        'If the email is not linked to any account, we tell you so — check the spelling or create an account.',
                        'The reset link expires after 60 minutes; request a new one if it lapses.',
                        'Resetting your password also clears an account locked by too many failed sign-ins.',
                    ],
                    'verified_by' => [
                        'Tests\Feature\Fixes\PasswordResetTest::test_requesting_a_reset_for_a_known_email_sends_the_link',
                        'Tests\Feature\Fixes\PasswordResetTest::test_a_valid_token_resets_the_password_clears_lockout_and_stamps_changed',
                        'Tests\Feature\Fixes\PasswordResetTest::test_the_reset_lets_the_user_sign_in_with_the_new_password',
                    ],
                ],
                [
                    'id' => 'find-your-way',
                    'title' => 'Find your way around',
                    'summary' => 'Everything lives in the left-hand menu.',
                    'steps' => [
                        '**Owner** — your dashboard, **My Properties**, **Analytics** and **Advertising**.',
                        '**Tenant Activity** — **Enquiry Inbox**, **Interests**, **Viewing Requests**, **Applications** and **Maintenance**.',
                        '**Billing & Documents** — **Rent & Income**, **Leases**, **Documents** and **Plan & Billing**.',
                        'The bell at the top shows your notifications: new enquiries, viewing requests, applications and more.',
                    ],
                    'links' => [
                        ['label' => 'Open my dashboard', 'route' => 'owner.dashboard'],
                    ],
                    'verified_by' => [
                        'Tests\Feature\DzimbaAccessControlTest::test_owner_can_access_owner_dashboard',
                        'Tests\Feature\MonetiseTest::test_owner_dashboard_exposes_the_ledger_based_financial_snapshot',
                    ],
                ],
                [
                    'id' => 'profile-photo',
                    'title' => 'Add a profile photo',
                    'summary' => 'Your photo appears on your profile button and beside your replies, so tenants know who they are dealing with.',
                    'steps' => [
                        'Open **Account settings** from your profile button (top right).',
                        'Upload a photo. Until you add one, your initials are shown instead.',
                        'Replace or remove it any time from the same page.',
                    ],
                    'notes' => [
                        'Photos must be an image under 4 MB.',
                        'Only you can change your own photo.',
                    ],
                    'links' => [
                        ['label' => 'Open account settings', 'route' => 'account.profile'],
                    ],
                    'verified_by' => [
                        'Tests\Feature\Fixes\ProfilePhotoTest::test_upload_sets_avatar_path_and_stores_the_file',
                        'Tests\Feature\Fixes\ProfilePhotoTest::test_replacing_the_photo_deletes_the_old_file',
                        'Tests\Feature\Fixes\ProfilePhotoTest::test_upload_rejects_non_image_files',
                        'Tests\Feature\Fixes\ProfilePhotoTest::test_a_user_cannot_change_another_users_avatar',
                        'Tests\Feature\Fixes\ProfilePhotoTest::test_account_profile_page_opens_for_each_role',
                    ],
                ],
                [
                    'id' => 'access-pass',
                    'title' => 'Your access pass',
                    'summary' => 'ZimRent is free to launch. Later, a small access pass may be needed to publish new listings.',
                    'steps' => [
                        'Everything is free right now — listing, replying and managing tenants all work with no pass.',
                        'When paid access begins, the **Access** page shows whether a pass is required for you, the price and how long it lasts.',
                        'If needed, buy a pass from that page and it grants access for the whole period.',
                    ],
                    'notes' => [
                        'Whether owners pay, when charging starts and the price are all set by ZimRent — nothing is hard-coded, and you are never blocked while access is free.',
                    ],
                    'links' => [
                        ['label' => 'View my access', 'route' => 'access.index'],
                    ],
                    'verified_by' => [
                        'Tests\Feature\Fixes\AccessPassTest::test_with_charging_off_nobody_requires_a_pass_and_all_actions_work',
                        'Tests\Feature\Fixes\AccessPassTest::test_after_free_until_owner_without_pass_is_redirected_from_listing_create',
                        'Tests\Feature\Fixes\AccessPassTest::test_payer_owner_does_not_gate_tenants',
                        'Tests\Feature\Fixes\AccessPassTest::test_buying_a_pass_grants_access_for_the_period',
                        'Tests\Feature\Fixes\AccessPassTest::test_expired_pass_blocks_again',
                    ],
                ],
            ],
        ],
        [
            'id' => 'listings',
            'title' => 'Your listings',
            'articles' => [
                [
                    'id' => 'create-listing',
                    'title' => 'List a property',
                    'summary' => 'The form is grouped into collapsible sections so you can work through it one part at a time.',
                    'steps' => [
                        'Open **My Properties** and choose to add a new property.',
                        'Work through the sections — **Basics**, **Pricing**, **Rental details**, **Location**, **Amenities** and **Photos** — filling in the title, description, type, bedrooms and bathrooms, rent, deposit and the rest.',
                        'In **Location**, drop the map pin on the exact spot. This is required — tenants see only an approximate area until you engage with them, so the exact pin stays private.',
                        'Save the listing. Only listings with the status **available** appear on the marketplace.',
                    ],
                    'notes' => [
                        'Required fields are checked before anything is saved — fix any highlighted fields and save again.',
                        'A property cannot be saved without a map pin.',
                        'On the Free plan you can publish one available listing at a time. Upgrade your plan to publish more.',
                    ],
                    'links' => [
                        ['label' => 'Add a property', 'route' => 'owner.properties.create'],
                        ['label' => 'Open my properties', 'route' => 'owner.properties.index'],
                    ],
                    'verified_by' => [
                        'Tests\Feature\ListingAndDiscoverTest::test_owner_can_create_a_property',
                        'Tests\Feature\ListingAndDiscoverTest::test_validation_errors_block_property_creation',
                        'Tests\Feature\ListingAndDiscoverTest::test_only_available_properties_appear_on_the_marketplace',
                        'Tests\Feature\Fixes\LocationPrivacyTest::test_creating_a_property_without_a_map_pin_is_rejected',
                        'Tests\Feature\Fixes\LocationPrivacyTest::test_owner_sees_the_exact_location',
                        'Tests\Feature\MonetiseTest::test_second_publish_is_blocked_on_free_plan_until_upgrade',
                    ],
                ],
                [
                    'id' => 'manage-listing',
                    'title' => 'Edit, change status or delete a listing',
                    'steps' => [
                        'Open **My Properties** and select a property.',
                        'Edit the details at any time, or change its status (for example available, reserved or unavailable). Every status change is recorded in the property history.',
                        'Delete a listing you no longer need.',
                    ],
                    'notes' => [
                        'Only allowed status changes are accepted — for example a leased home becomes occupied automatically when both parties sign.',
                        'Putting a listing back to available counts towards your plan’s listing limit.',
                    ],
                    'links' => [
                        ['label' => 'Open my properties', 'route' => 'owner.properties.index'],
                    ],
                    'verified_by' => [
                        'Tests\Feature\ListingAndDiscoverTest::test_owner_can_update_own_property',
                        'Tests\Feature\ListingAndDiscoverTest::test_allowed_status_transitions_change_status_and_write_history',
                        'Tests\Feature\ListingAndDiscoverTest::test_blocked_status_transitions_are_rejected_without_history',
                        'Tests\Feature\ListingAndDiscoverTest::test_owner_can_delete_own_property',
                        'Tests\Feature\MonetiseTest::test_returning_a_listing_to_available_is_quota_gated',
                    ],
                ],
                [
                    'id' => 'renew-listing',
                    'title' => 'Keep your listing live (expiry and renewal)',
                    'summary' => 'Listings expire after a set number of days so the marketplace stays fresh. You get a reminder before yours expires.',
                    'steps' => [
                        'When you receive an expiry reminder, open the property from **My Properties**.',
                        'Renew it. A recently expired listing can still be renewed within the grace window.',
                    ],
                    'links' => [
                        ['label' => 'Open my properties', 'route' => 'owner.properties.index'],
                    ],
                    'verified_by' => [
                        'Tests\Feature\ListingAndDiscoverTest::test_listing_lifecycle_expires_overdue_listings_with_history',
                        'Tests\Feature\ListingAndDiscoverTest::test_expiry_reminder_notifies_owners_within_the_configured_window',
                        'Tests\Feature\ListingAndDiscoverTest::test_owner_can_renew_a_recently_expired_listing',
                        'Tests\Feature\ListingAndDiscoverTest::test_expired_listing_outside_the_grace_window_cannot_be_renewed',
                    ],
                ],
            ],
        ],
        [
            'id' => 'tenants',
            'title' => 'Working with tenants',
            'articles' => [
                [
                    'id' => 'enquiries',
                    'title' => 'Answer enquiries',
                    'steps' => [
                        'You are notified when a tenant asks about one of your properties.',
                        'Open **Enquiry Inbox** and select the conversation — it is marked as read.',
                        'Write your reply and send it. Close the conversation when it is resolved.',
                    ],
                    'notes' => ['A reply cannot be empty, and a closed conversation cannot be replied to.'],
                    'links' => [
                        ['label' => 'Open enquiry inbox', 'route' => 'owner.enquiries.index'],
                    ],
                    'verified_by' => [
                        'Tests\Feature\EngageTest::test_owner_gets_notified_when_tenant_enquires',
                        'Tests\Feature\EngageTest::test_owner_show_marks_enquiry_as_read',
                        'Tests\Feature\EngageTest::test_owner_can_reply_and_thread_moves_to_replied',
                        'Tests\Feature\EngageTest::test_reply_requires_a_body',
                        'Tests\Feature\EngageTest::test_owner_can_close_an_enquiry',
                        'Tests\Feature\EngageTest::test_cannot_reply_to_a_closed_thread',
                    ],
                ],
                [
                    'id' => 'live-chat',
                    'title' => 'Chat live with tenants',
                    'summary' => 'Tenants can open a direct conversation with you from your property page — reply in real time.',
                    'steps' => [
                        'When a tenant messages you, it appears in the chat box in the bottom corner of any signed-in page, and you are notified.',
                        'Reply straight from the chat box, or open **Messages** to see every conversation.',
                        'Each tenant–property conversation is a single thread, so nothing gets scattered.',
                    ],
                    'notes' => [
                        'Need ZimRent staff? Use **Contact support** in the chat box.',
                    ],
                    'links' => [
                        ['label' => 'Open my messages', 'route' => 'chat.index'],
                    ],
                    'verified_by' => [
                        'Tests\Feature\Fixes\ChatTest::test_starting_a_direct_chat_creates_one_conversation_with_tenant_and_owner',
                        'Tests\Feature\Fixes\ChatTest::test_a_participant_can_post_a_message',
                        'Tests\Feature\Fixes\ChatTest::test_a_non_participant_cannot_post_to_a_conversation',
                        'Tests\Feature\Fixes\ChatTest::test_unread_count_tracks_new_messages_and_mark_read_clears_it',
                        'Tests\Feature\Fixes\ChatTest::test_any_authenticated_role_can_open_the_chat_page',
                    ],
                ],
                [
                    'id' => 'interests',
                    'title' => 'Follow up on interested tenants',
                    'summary' => 'Tenants can express interest in one tap. **Interests** collects them per property, with the tenant’s profile and badge so you can decide who to contact.',
                    'steps' => [
                        'Open **Interests**. Filter by property or status if you have many.',
                        'Contact the tenant, then mark the interest as contacted.',
                        'Move it back to the active queue if needed, or archive it when you are done.',
                    ],
                    'links' => [
                        ['label' => 'Open interests', 'route' => 'owner.interests.index'],
                    ],
                    'verified_by' => [
                        'Tests\Feature\DzimbaAccessControlTest::test_owner_can_access_interest_queue',
                        'Tests\Feature\InterestsTest::test_owner_can_contact_then_reopen_an_interest',
                        'Tests\Feature\InterestsTest::test_owner_can_archive_an_interest',
                    ],
                ],
                [
                    'id' => 'viewings',
                    'title' => 'Offer viewing times and handle requests',
                    'summary' => 'Manage availability on a calendar and act on every request — including times a tenant suggests — in one place.',
                    'steps' => [
                        'From a property in **My Properties**, open its **Viewing Calendar**. Pick a day, then add the times you are available — slots must be in the future and end after they start. Tenant requests show on the same calendar so you can see demand at a glance.',
                        'Tenants either take one of your open slots or suggest a time of their own. You are notified of each request.',
                        'In **Viewing Requests**, accept, decline or propose another slot. A tenant-suggested time is flagged; accepting it creates and locks that slot automatically so it cannot be double-booked.',
                        'After the visit, mark it as completed or as a no-show.',
                    ],
                    'notes' => [
                        'A locked slot never disappears from your calendar — it shows as **Booked** with the tenant’s name.',
                    ],
                    'links' => [
                        ['label' => 'Open viewing requests', 'route' => 'owner.viewings.index'],
                    ],
                    'verified_by' => [
                        'Tests\Feature\EngageTest::test_owner_can_create_a_viewing_slot',
                        'Tests\Feature\EngageTest::test_slot_start_must_be_in_the_future',
                        'Tests\Feature\EngageTest::test_owner_gets_notified_when_tenant_requests_viewing',
                        'Tests\Feature\EngageTest::test_accept_locks_the_slot',
                        'Tests\Feature\EngageTest::test_accepting_a_suggested_time_creates_and_locks_a_slot',
                        'Tests\Feature\EngageTest::test_double_booking_is_rejected',
                        'Tests\Feature\EngageTest::test_reschedule_proposes_another_slot_then_tenant_confirms',
                        'Tests\Feature\EngageTest::test_owner_can_mark_completed_and_no_show',
                    ],
                ],
                [
                    'id' => 'applications',
                    'title' => 'Review applications',
                    'steps' => [
                        'Open **Applications** — they are grouped by property. You are notified of each new one.',
                        'Shortlist promising applicants, reject others (a reason is required and is shared with the tenant), and approve the one you choose.',
                    ],
                    'notes' => [
                        'Only one application can be approved per property. Approving does not change the listing yet — that happens when you create the lease.',
                    ],
                    'links' => [
                        ['label' => 'Open applications', 'route' => 'owner.applications.index'],
                    ],
                    'verified_by' => [
                        'Tests\Feature\CommitTest::test_owner_is_notified_when_tenant_applies',
                        'Tests\Feature\CommitTest::test_shortlist_toggles_between_pending_and_shortlisted',
                        'Tests\Feature\CommitTest::test_reject_requires_a_reason',
                        'Tests\Feature\CommitTest::test_owner_approves_an_application_without_moving_the_property',
                        'Tests\Feature\CommitTest::test_only_one_application_can_be_approved_per_property',
                    ],
                ],
            ],
        ],
        [
            'id' => 'leases-rent',
            'title' => 'Leases and rent',
            'articles' => [
                [
                    'id' => 'create-lease',
                    'title' => 'Create and sign a lease',
                    'steps' => [
                        'From an approved application, create the lease and set the start and end dates (the end must be after the start). The property is reserved and the other applicants are closed automatically.',
                        'Open **Leases** and send the draft lease to the tenant for signature.',
                        'Sign it yourself. When both of you have signed, the lease becomes active and the property is marked occupied.',
                        'The signed agreement is stored in **Documents**.',
                    ],
                    'notes' => [
                        'Near the end of an active lease you can renew it: the terms are copied and you can adjust the dates. Only one renewal can be in progress at a time.',
                    ],
                    'links' => [
                        ['label' => 'Open leases', 'route' => 'owner.leases.index'],
                        ['label' => 'Open documents', 'route' => 'owner.documents.index'],
                    ],
                    'verified_by' => [
                        'Tests\Feature\CommitTest::test_pipeline_apply_approve_lease_reserves_property',
                        'Tests\Feature\CommitTest::test_lease_end_date_must_fall_after_start_date',
                        'Tests\Feature\CommitTest::test_generating_a_lease_auto_rejects_remaining_active_applicants',
                        'Tests\Feature\CommitTest::test_owner_sends_draft_lease_for_signature',
                        'Tests\Feature\CommitTest::test_both_signatures_activate_the_lease_and_occupy_the_property',
                        'Tests\Feature\CommitTest::test_both_signatures_store_an_agreement_document',
                        'Tests\Feature\CommitTest::test_owner_renews_an_active_lease_copying_the_terms',
                        'Tests\Feature\CommitTest::test_only_one_renewal_can_be_in_flight_at_a_time',
                    ],
                ],
                [
                    'id' => 'rent-income',
                    'title' => 'Track rent and income',
                    'summary' => 'When a lease becomes active, ZimRent creates its full rent schedule with an invoice for each period.',
                    'steps' => [
                        'Open **Rent & Income** to see invoices that are upcoming, due, overdue and paid for each of your properties, and your arrears.',
                        'Your dashboard shows this month’s income and your occupancy rate.',
                    ],
                    'notes' => [
                        'Tenants pay through ZimRent. Some payments are checked by ZimRent staff before they count as paid.',
                        'You and the tenant are both notified when an invoice falls overdue.',
                    ],
                    'links' => [
                        ['label' => 'Open rent & income', 'route' => 'owner.rent.index'],
                    ],
                    'verified_by' => [
                        'Tests\Feature\MonetiseTest::test_active_lease_generates_full_term_schedule_and_invoices',
                        'Tests\Feature\MonetiseTest::test_owner_arrear_statement_only_includes_own_properties',
                        'Tests\Feature\MonetiseTest::test_owner_income_aggregation_totals_settled_payments_for_the_month',
                        'Tests\Feature\MonetiseTest::test_occupancy_rate_is_computed_from_owned_property_statuses',
                        'Tests\Feature\MonetiseTest::test_falling_overdue_notifies_the_tenant_and_the_property_owner_once',
                    ],
                ],
                [
                    'id' => 'maintenance',
                    'title' => 'Handle maintenance requests',
                    'steps' => [
                        'Tenants with an active lease report problems. Emergencies notify you immediately.',
                        'Open **Maintenance**, choose a verified contractor and enter their quote to assign the job.',
                        'The contractor starts and completes the job; the tenant confirms the fix; then you close the request.',
                        'After closing, rate the contractor. Each job can be rated once.',
                    ],
                    'links' => [
                        ['label' => 'Open maintenance', 'route' => 'owner.maintenance.index'],
                    ],
                    'verified_by' => [
                        'Tests\Feature\OperateTest::test_emergency_report_notifies_owner_and_staff_immediately',
                        'Tests\Feature\OperateTest::test_owner_assigns_verified_contractor_with_quote',
                        'Tests\Feature\OperateTest::test_only_verified_contractors_can_be_assigned',
                        'Tests\Feature\OperateTest::test_tenant_confirms_the_fix_once_then_owner_closes',
                        'Tests\Feature\OperateTest::test_owner_rates_contractor_after_close',
                        'Tests\Feature\OperateTest::test_one_rating_per_request',
                    ],
                ],
            ],
        ],
        [
            'id' => 'growing',
            'title' => 'Plans, promotion and insights',
            'articles' => [
                [
                    'id' => 'plans',
                    'title' => 'Choose a plan',
                    'summary' => 'Every owner starts on the Free plan. Paid plans raise how many listings you can have available at once.',
                    'steps' => [
                        'Open **Plan & Billing** to see your current plan and the available plans.',
                        'Choose a plan. Upgrades take effect immediately with the remaining value of your current cycle credited; downgrades take effect at the end of your cycle.',
                    ],
                    'notes' => [
                        'If a paid cycle lapses, your plan goes into a grace period and then suspension. While suspended you fall back to the Free plan’s listing limit.',
                    ],
                    'links' => [
                        ['label' => 'Open plan & billing', 'route' => 'owner.subscriptions.index'],
                    ],
                    'verified_by' => [
                        'Tests\Feature\MonetiseTest::test_owner_without_subscription_is_lazily_placed_on_free_plan',
                        'Tests\Feature\MonetiseTest::test_upgrade_prorates_the_remaining_cycle_value',
                        'Tests\Feature\MonetiseTest::test_downgrade_is_deferred_to_the_end_of_the_cycle',
                        'Tests\Feature\MonetiseTest::test_lapsed_cycle_moves_to_grace_then_suspension',
                        'Tests\Feature\MonetiseTest::test_suspended_owner_falls_back_to_free_plan_quota',
                    ],
                ],
                [
                    'id' => 'advertising',
                    'title' => 'Promote a listing',
                    'summary' => 'Featured listings appear first on the marketplace.',
                    'steps' => [
                        'Open **Advertising** and choose one of your available listings and a promotion package.',
                        'Book it. The promotion goes live once ZimRent approves it, and you are notified.',
                    ],
                    'notes' => [
                        'Each property can hold one promotion at a time, and only available listings can be promoted.',
                        'If a promotion is cancelled you receive a credit for the unused part.',
                    ],
                    'links' => [
                        ['label' => 'Open advertising', 'route' => 'owner.advertising.index'],
                    ],
                    'verified_by' => [
                        'Tests\Feature\AdsTest::test_owner_can_book_a_promotion',
                        'Tests\Feature\AdsTest::test_only_an_available_listing_can_be_promoted',
                        'Tests\Feature\AdsTest::test_a_property_holds_a_single_placement_slot',
                        'Tests\Feature\AdsTest::test_admin_approval_settles_and_opens_the_promotion_window',
                        'Tests\Feature\AdsTest::test_cancelling_an_active_window_prorates_the_unused_portion',
                    ],
                ],
                [
                    'id' => 'analytics',
                    'title' => 'See how your listings perform',
                    'steps' => [
                        'Open **Analytics** for views, saves, enquiries and applications across your own listings.',
                    ],
                    'links' => [
                        ['label' => 'Open analytics', 'route' => 'owner.analytics.index'],
                    ],
                    'verified_by' => [
                        'Tests\Feature\TrustGovernTest::test_owner_analytics_page_reports_own_portfolio_performance',
                        'Tests\Feature\TrustGovernTest::test_owner_analytics_never_leaks_other_owners_properties',
                    ],
                ],
            ],
        ],
    ],
];
