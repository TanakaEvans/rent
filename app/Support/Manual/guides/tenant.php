<?php

/*
 * Tenant guide. Every article cites the feature tests that prove it
 * (verified_by) — see tests/Feature/UserManualTest.
 */

return [
    'key' => 'tenant',
    'title' => 'Tenant guide',
    'tagline' => 'Find a home, talk to the owner directly, sign your lease and pay rent — all in one place.',
    'role' => 'Tenant',
    'sections' => [
        [
            'id' => 'getting-started',
            'title' => 'Getting started',
            'intro' => 'You can browse every listing without an account. Create a free tenant account when you want to save homes, contact owners or apply.',
            'articles' => [
                [
                    'id' => 'create-account',
                    'title' => 'Create your tenant account',
                    'summary' => 'Signing up is free and takes under a minute. New accounts are tenant accounts.',
                    'steps' => [
                        'Select **Create account** at the top of any page (or **Sign in**, then **Create a free tenant account**).',
                        'Enter your name, email address and a password, confirm the password and accept the terms.',
                        'Submit the form. You are signed in straight away and taken to your tenant dashboard.',
                    ],
                    'notes' => [
                        'Each email address can only be used for one account.',
                        'Weak passwords are rejected — use a longer password with a mix of letters and numbers.',
                        'Your username is created automatically from your email address.',
                    ],
                    'verified_by' => [
                        'Tests\Feature\SignupTest::test_public_signup_creates_a_tenant_with_default_role',
                        'Tests\Feature\SignupTest::test_signup_auto_logs_in_the_new_tenant',
                        'Tests\Feature\SignupTest::test_duplicate_email_is_rejected',
                        'Tests\Feature\SignupTest::test_weak_password_is_rejected',
                        'Tests\Feature\SignupTest::test_missing_terms_are_rejected',
                        'Tests\Feature\SignupTest::test_username_is_derived_from_the_email',
                    ],
                ],
                [
                    'id' => 'find-your-way',
                    'title' => 'Find your way around',
                    'summary' => 'After signing in, everything you do lives in the left-hand menu of your dashboard.',
                    'steps' => [
                        '**Tenant Dashboard** gives you an overview of your activity.',
                        '**My Activity** holds your favourites, saved searches, enquiries, interests, viewings, applications, reports and maintenance requests.',
                        '**Billing & Documents** holds your rent, leases and signed documents.',
                        'Use **Marketplace** at the top of the menu to go back to browsing homes, and the bell icon for notifications.',
                    ],
                    'links' => [
                        ['label' => 'Open my dashboard', 'route' => 'tenant.dashboard'],
                    ],
                    'verified_by' => [
                        'Tests\Feature\DzimbaAccessControlTest::test_tenant_can_access_tenant_dashboard',
                        'Tests\Feature\DzimbaAccessControlTest::test_tenant_cannot_access_owner_dashboard',
                    ],
                ],
                [
                    'id' => 'profile',
                    'title' => 'Complete your profile and get verified',
                    'summary' => 'Owners see your profile and verification badge when you contact them, so a complete profile gets faster replies.',
                    'steps' => [
                        'Open **My Profile** from the menu.',
                        'Fill in your contact and background details and save.',
                        'Upload your identity evidence (for example your ID). Uploading the same document type again replaces the previous file.',
                        'ZimRent staff review each document. Approved evidence earns you a **Silver**, then **Gold** badge, and you are notified of every decision.',
                    ],
                    'notes' => [
                        'Your documents are stored privately and are never shown publicly. Only you and ZimRent staff can open them.',
                        'If a document is rejected you receive the reason, and you can upload a corrected one.',
                    ],
                    'links' => [
                        ['label' => 'Open my profile', 'route' => 'tenant.profile'],
                    ],
                    'verified_by' => [
                        'Tests\Feature\TenantProfileTest::test_tenant_can_update_their_profile',
                        'Tests\Feature\TenantProfileTest::test_tenant_can_upload_kyc_evidence_to_the_private_disk',
                        'Tests\Feature\TenantProfileTest::test_uploading_the_same_type_replaces_the_file_and_audits_it',
                        'Tests\Feature\TenantProfileTest::test_a_tenant_cannot_see_another_tenants_documents',
                        'Tests\Feature\KycAdminTest::test_approving_evidence_earns_silver_then_gold_and_notifies',
                        'Tests\Feature\KycAdminTest::test_rejecting_with_a_note_keeps_the_trail_and_sends_the_reason',
                    ],
                ],
                [
                    'id' => 'profile-photo',
                    'title' => 'Add a profile photo',
                    'summary' => 'Your photo appears on your profile button and beside your messages once you sign in.',
                    'steps' => [
                        'Open **Account settings** from your profile button (top right).',
                        'Upload a photo. Until you add one, your initials are shown instead.',
                        'You can replace or remove the photo at any time from the same page.',
                    ],
                    'notes' => [
                        'Photos must be an image under 4 MB. Other file types are rejected.',
                        'Only you can change your own photo.',
                    ],
                    'links' => [
                        ['label' => 'Open account settings', 'route' => 'account.profile'],
                    ],
                    'verified_by' => [
                        'Tests\Feature\Fixes\ProfilePhotoTest::test_upload_sets_avatar_path_and_stores_the_file',
                        'Tests\Feature\Fixes\ProfilePhotoTest::test_replacing_the_photo_deletes_the_old_file',
                        'Tests\Feature\Fixes\ProfilePhotoTest::test_delete_removes_the_file_and_nulls_the_column',
                        'Tests\Feature\Fixes\ProfilePhotoTest::test_upload_rejects_non_image_files',
                        'Tests\Feature\Fixes\ProfilePhotoTest::test_upload_rejects_images_over_four_megabytes',
                        'Tests\Feature\Fixes\ProfilePhotoTest::test_a_user_cannot_change_another_users_avatar',
                    ],
                ],
                [
                    'id' => 'access-pass',
                    'title' => 'Your access pass',
                    'summary' => 'ZimRent is free to launch. Later, a small access pass may be needed for some actions.',
                    'steps' => [
                        'Everything is free right now — browsing, enquiring, viewings and applications all work with no pass.',
                        'When paid access begins, the **Access** page shows whether a pass is required for you, the price and how long it lasts.',
                        'If needed, buy a pass from that page and it grants access for the whole period.',
                    ],
                    'notes' => [
                        'Whether tenants pay at all, when charging starts and the price are all set by ZimRent — nothing is hard-coded, and you are never blocked while access is free.',
                    ],
                    'links' => [
                        ['label' => 'View my access', 'route' => 'access.index'],
                    ],
                    'verified_by' => [
                        'Tests\Feature\Fixes\AccessPassTest::test_with_charging_off_nobody_requires_a_pass_and_all_actions_work',
                        'Tests\Feature\Fixes\AccessPassTest::test_charging_on_before_free_until_is_still_free',
                        'Tests\Feature\Fixes\AccessPassTest::test_payer_tenant_gates_tenants_but_not_owners',
                        'Tests\Feature\Fixes\AccessPassTest::test_buying_a_pass_grants_access_for_the_period',
                        'Tests\Feature\Fixes\AccessPassTest::test_access_page_renders_with_status',
                    ],
                ],
            ],
        ],
        [
            'id' => 'finding-a-home',
            'title' => 'Finding a home',
            'articles' => [
                [
                    'id' => 'search-and-filter',
                    'title' => 'Search and filter listings',
                    'summary' => 'Only homes that are currently available are shown on the marketplace.',
                    'steps' => [
                        'Type a suburb, city or property name in the search bar on the home page and press **Search**. Suggestions appear as you type.',
                        'Narrow results with the filters: property type, city, area, bedrooms, bathrooms, payment terms, furnishing and a minimum/maximum rent.',
                        'Open **More filters** for verified listings, availability, security, parking, preferred tenant and amenities.',
                        'Change the order with the sort menu (newest, price, best value per m², featured, top rated) and switch between **Grid**, **List** and **Map**.',
                    ],
                    'notes' => [
                        'Bedroom and bathroom filters mean “at least” — 2+ beds also shows 3 and 4 bedroom homes.',
                        'Min and max rent are applied together as a true price range.',
                    ],
                    'links' => [
                        ['label' => 'Browse homes', 'route' => 'home'],
                    ],
                    'verified_by' => [
                        'Tests\Feature\ListingAndDiscoverTest::test_only_available_properties_appear_on_the_marketplace',
                        'Tests\Feature\ListingAndDiscoverTest::test_marketplace_keyword_search_matches_title_suburb_and_description',
                        'Tests\Feature\ListingAndDiscoverTest::test_search_suggestions_return_places_and_property_titles',
                        'Tests\Feature\ListingAndDiscoverTest::test_marketplace_filter_combination_returns_exact_set',
                        'Tests\Feature\ListingAndDiscoverTest::test_marketplace_bedrooms_and_bathrooms_use_at_least_semantics',
                        'Tests\Feature\ListingAndDiscoverTest::test_marketplace_price_applies_as_true_min_max_range',
                        'Tests\Feature\ListingAndDiscoverTest::test_marketplace_sorts_by_price_per_square_metre',
                    ],
                ],
                [
                    'id' => 'favourites',
                    'title' => 'Save homes you like',
                    'summary' => 'Tap the heart on any listing to save it. Tap it again to remove it.',
                    'steps' => [
                        'Select the heart icon on a listing card or on the property page (you will be asked to sign in first if you are a guest).',
                        'Open **My Favourites** in the menu to see everything you saved.',
                    ],
                    'links' => [
                        ['label' => 'Open my favourites', 'route' => 'tenant.favourites.index'],
                    ],
                    'verified_by' => [
                        'Tests\Feature\ListingAndDiscoverTest::test_tenant_can_toggle_a_favourite_on_and_off',
                        'Tests\Feature\ListingAndDiscoverTest::test_tenant_favourites_page_lists_saved_properties',
                        'Tests\Feature\ListingAndDiscoverTest::test_guest_is_redirected_to_login_when_opening_favourites',
                    ],
                ],
                [
                    'id' => 'saved-searches',
                    'title' => 'Save a search and get alerts',
                    'summary' => 'Save the filters you use most and ZimRent tells you when new matching homes are listed.',
                    'steps' => [
                        'Set your filters on the marketplace, then select **Save search** above the results.',
                        'Give the search a name (a suggested name is filled in for you).',
                        'Manage it from **Saved Searches**: rename it, switch alerts on or off, or delete it. Each saved search shows how many homes currently match.',
                    ],
                    'links' => [
                        ['label' => 'Open saved searches', 'route' => 'tenant.saved-searches.index'],
                    ],
                    'verified_by' => [
                        'Tests\Feature\ListingAndDiscoverTest::test_tenant_can_create_rename_toggle_and_delete_a_saved_search',
                        'Tests\Feature\ListingAndDiscoverTest::test_saved_search_index_reports_a_live_match_count',
                    ],
                ],
            ],
        ],
        [
            'id' => 'contacting-owners',
            'title' => 'Contacting owners and viewing',
            'intro' => 'Every listing is managed directly by its owner — there is no agent in between. These actions are on the property page.',
            'articles' => [
                [
                    'id' => 'enquiries',
                    'title' => 'Ask the owner a question',
                    'steps' => [
                        'Open a property and write your question in the enquiry box, then send it.',
                        'You are notified when the owner replies. Follow the conversation in **My Enquiries**.',
                    ],
                    'notes' => [
                        'A message is required and can be up to 1,000 characters.',
                        'You can only have one open enquiry per property — wait for the reply before sending another.',
                        'Enquiries can only be sent for homes that are still available.',
                    ],
                    'links' => [
                        ['label' => 'Open my enquiries', 'route' => 'tenant.enquiries.index'],
                    ],
                    'verified_by' => [
                        'Tests\Feature\EngageTest::test_tenant_can_send_an_enquiry',
                        'Tests\Feature\EngageTest::test_enquiry_requires_a_message',
                        'Tests\Feature\EngageTest::test_enquiry_message_is_limited_to_1000_chars',
                        'Tests\Feature\EngageTest::test_duplicate_open_enquiry_is_blocked',
                        'Tests\Feature\EngageTest::test_cannot_enquire_about_unavailable_property',
                        'Tests\Feature\EngageTest::test_tenant_gets_notified_when_owner_replies',
                    ],
                ],
                [
                    'id' => 'live-chat',
                    'title' => 'Chat live with the owner',
                    'summary' => 'Prefer a back-and-forth conversation? Message the owner directly and reply in real time.',
                    'steps' => [
                        'On a property page choose **Message owner (live chat)** to open a direct conversation with that owner.',
                        'Type in the chat box in the bottom corner of any signed-in page. New messages raise a badge, and the owner is notified.',
                        'All your conversations live in **Messages** — pick one up again any time.',
                    ],
                    'notes' => [
                        'Messaging the same owner about the same property always reuses one conversation, so nothing gets scattered.',
                        'Need help from ZimRent instead of an owner? Use **Contact support** in the chat box to reach our staff.',
                    ],
                    'links' => [
                        ['label' => 'Open my messages', 'route' => 'chat.index'],
                    ],
                    'verified_by' => [
                        'Tests\Feature\Fixes\ChatTest::test_starting_a_direct_chat_creates_one_conversation_with_tenant_and_owner',
                        'Tests\Feature\Fixes\ChatTest::test_starting_a_direct_chat_twice_returns_the_same_conversation',
                        'Tests\Feature\Fixes\ChatTest::test_a_participant_can_post_a_message',
                        'Tests\Feature\Fixes\ChatTest::test_support_chat_reaches_admins_who_can_reply',
                        'Tests\Feature\Fixes\ChatTest::test_unread_count_tracks_new_messages_and_mark_read_clears_it',
                        'Tests\Feature\Fixes\ChatTest::test_any_authenticated_role_can_open_the_chat_page',
                    ],
                ],
                [
                    'id' => 'exact-location',
                    'title' => 'Seeing a home’s exact location',
                    'summary' => 'For privacy, exact addresses are only revealed once an owner is engaging with you.',
                    'steps' => [
                        'Before then, the map shows only an approximate area (a shaded circle) around the home — enough to judge the neighbourhood.',
                        'The exact pin and street address appear once the owner accepts your viewing, approves your application, or you are on an open lease for the home.',
                        'When it unlocks, **My Viewings** shows a **Get directions** button straight to Google Maps.',
                    ],
                    'notes' => [
                        'The approximate area is stable for a home but never gives away the real point, so it is safe to share.',
                    ],
                    'links' => [
                        ['label' => 'Open my viewings', 'route' => 'tenant.viewings.index'],
                    ],
                    'verified_by' => [
                        'Tests\Feature\Fixes\LocationPrivacyTest::test_guest_sees_only_the_approximate_area',
                        'Tests\Feature\Fixes\LocationPrivacyTest::test_tenant_with_a_pending_viewing_still_sees_only_the_approximate_area',
                        'Tests\Feature\Fixes\LocationPrivacyTest::test_tenant_with_an_accepted_viewing_sees_the_exact_location',
                        'Tests\Feature\Fixes\LocationPrivacyTest::test_tenant_with_an_approved_application_sees_the_exact_location',
                        'Tests\Feature\Fixes\LocationPrivacyTest::test_tenant_on_an_open_lease_sees_the_exact_location',
                        'Tests\Feature\Fixes\LocationPrivacyTest::test_marketplace_map_never_exposes_exact_coordinates',
                    ],
                ],
                [
                    'id' => 'express-interest',
                    'title' => 'Express interest in one tap',
                    'summary' => 'Not ready to write a message? Express interest and the owner will contact you.',
                    'steps' => [
                        'On an available property, choose **Express interest** and add an optional note.',
                        'Track it in **My Interests**. You can withdraw an interest at any time, and expressing interest again re-opens it.',
                    ],
                    'notes' => [
                        'The note is optional and limited to 500 characters.',
                        'Tapping twice never creates a duplicate — the owner sees you once per property.',
                    ],
                    'links' => [
                        ['label' => 'Open my interests', 'route' => 'tenant.interests.index'],
                    ],
                    'verified_by' => [
                        'Tests\Feature\InterestsTest::test_tenant_can_express_interest',
                        'Tests\Feature\InterestsTest::test_interest_is_spam_free_and_idempotent',
                        'Tests\Feature\InterestsTest::test_note_is_optional_and_limited_500_chars',
                        'Tests\Feature\InterestsTest::test_tenant_can_withdraw_own_interest',
                        'Tests\Feature\InterestsTest::test_re_express_reopens_an_archived_row',
                        'Tests\Feature\UserFacingErrorsTest::test_tenant_can_view_their_interests_page_with_existing_interests',
                    ],
                ],
                [
                    'id' => 'viewings',
                    'title' => 'Book a viewing',
                    'summary' => 'Book on a calendar in two ways: take one of the owner’s open times, or suggest a time that suits you.',
                    'steps' => [
                        'On the property page, open **Book a viewing**. In **Open times**, pick a day on the calendar and choose one of the owner’s open slots for that day, add an optional message, then request the viewing.',
                        'Prefer a different time? Switch to **Suggest a time**, choose a start and end that suit you, add a note and send it — even when the owner has no open slots yet.',
                        'The owner accepts, declines or proposes another time. When they accept your suggested time it becomes a locked slot. If they propose a new time, confirm it in **My Viewings**.',
                        'Track everything on the calendar in **My Viewings** — confirmed, awaiting-owner and completed viewings are colour-coded — and cancel a booking there if your plans change.',
                    ],
                    'notes' => [
                        'Once a time is accepted it is locked for you and nobody else can book it.',
                        'You can only have one suggested time per property at once — accept, cancel or wait on it before suggesting another.',
                        'When the owner accepts, **My Viewings** shows the exact address with a **Get directions** button to Google Maps.',
                        'You are notified when the owner accepts or reschedules.',
                    ],
                    'links' => [
                        ['label' => 'Open my viewings', 'route' => 'tenant.viewings.index'],
                    ],
                    'verified_by' => [
                        'Tests\Feature\EngageTest::test_tenant_can_request_a_viewing',
                        'Tests\Feature\EngageTest::test_tenant_can_suggest_their_own_viewing_time',
                        'Tests\Feature\EngageTest::test_suggested_time_must_be_in_the_future_and_end_after_start',
                        'Tests\Feature\EngageTest::test_accepting_a_suggested_time_creates_and_locks_a_slot',
                        'Tests\Feature\EngageTest::test_a_tenant_cannot_stack_two_suggested_times_on_one_property',
                        'Tests\Feature\EngageTest::test_accept_locks_the_slot',
                        'Tests\Feature\EngageTest::test_reschedule_proposes_another_slot_then_tenant_confirms',
                        'Tests\Feature\EngageTest::test_cancel_releases_a_locked_slot',
                        'Tests\Feature\EngageTest::test_tenant_gets_notified_when_viewing_accepted',
                    ],
                ],
            ],
        ],
        [
            'id' => 'renting',
            'title' => 'Applying and signing',
            'articles' => [
                [
                    'id' => 'apply',
                    'title' => 'Apply for a home',
                    'steps' => [
                        'On an available property, write a short message about yourself (optional) and submit your application.',
                        'Follow its progress in **My Applications**: pending, shortlisted, approved or rejected.',
                    ],
                    'notes' => [
                        'You can have one active application per property. If it is rejected you will see the owner’s reason, and you may apply again.',
                        'The application message can be up to 1,000 characters.',
                        'When the owner turns an approved application into a lease, other applications for that home are closed automatically.',
                    ],
                    'links' => [
                        ['label' => 'Open my applications', 'route' => 'tenant.applications.index'],
                    ],
                    'verified_by' => [
                        'Tests\Feature\CommitTest::test_tenant_can_apply_to_an_available_property',
                        'Tests\Feature\CommitTest::test_application_message_is_limited_to_1000_characters',
                        'Tests\Feature\CommitTest::test_tenant_cannot_hold_two_active_applications_for_one_property',
                        'Tests\Feature\CommitTest::test_tenant_can_reapply_after_a_rejection',
                        'Tests\Feature\CommitTest::test_reject_records_reason_and_notifies_tenant',
                        'Tests\Feature\CommitTest::test_generating_a_lease_auto_rejects_remaining_active_applicants',
                    ],
                ],
                [
                    'id' => 'sign-lease',
                    'title' => 'Sign your lease',
                    'steps' => [
                        'When the owner sends your lease for signature, open **My Leases**.',
                        'Review the terms and sign. The lease becomes active once both you and the owner have signed.',
                        'The signed agreement is saved automatically in **My Documents**, where you can view or download it.',
                    ],
                    'notes' => [
                        'Each party signs once. A lease cannot be signed before the owner sends it.',
                        'Renewals work the same way: the owner sends a renewal, and once signed it replaces the old lease.',
                    ],
                    'links' => [
                        ['label' => 'Open my leases', 'route' => 'tenant.leases.index'],
                        ['label' => 'Open my documents', 'route' => 'tenant.documents.index'],
                    ],
                    'verified_by' => [
                        'Tests\Feature\CommitTest::test_both_signatures_activate_the_lease_and_occupy_the_property',
                        'Tests\Feature\CommitTest::test_a_party_cannot_sign_twice',
                        'Tests\Feature\CommitTest::test_lease_cannot_be_signed_before_it_is_sent',
                        'Tests\Feature\CommitTest::test_both_signatures_store_an_agreement_document',
                        'Tests\Feature\CommitTest::test_tenant_can_download_their_agreement',
                        'Tests\Feature\CommitTest::test_signing_a_renewal_activates_it_and_marks_the_original_renewed',
                    ],
                ],
            ],
        ],
        [
            'id' => 'living-there',
            'title' => 'Rent and repairs',
            'articles' => [
                [
                    'id' => 'pay-rent',
                    'title' => 'Pay your rent',
                    'summary' => 'Your full rent schedule is created as soon as your lease is active. Each period has its own invoice.',
                    'steps' => [
                        'Open **My Rent** to see upcoming, due and overdue invoices and your balance.',
                        'Choose the invoice you want to pay and record your payment.',
                        'Once the payment is settled, download your receipt from the same page.',
                    ],
                    'notes' => [
                        'The amount must match the invoice exactly.',
                        'Some payments (for example large amounts) are checked by ZimRent staff before the invoice is marked paid. If a payment is rejected the invoice stays open and you can pay again.',
                        'Bank payments may require proof of payment to be attached.',
                        'You get a reminder when an invoice falls overdue, and late fees may apply as set out in your lease.',
                    ],
                    'links' => [
                        ['label' => 'Open my rent', 'route' => 'tenant.rent.index'],
                    ],
                    'verified_by' => [
                        'Tests\Feature\MonetiseTest::test_signing_a_lease_generates_its_rent_schedule',
                        'Tests\Feature\MonetiseTest::test_payment_must_match_the_invoice_amount_exactly',
                        'Tests\Feature\MonetiseTest::test_exact_cash_payment_settles_invoice_immediately_and_emits_receipt_notification',
                        'Tests\Feature\MonetiseTest::test_high_value_payment_waits_for_staff_then_approval_settles_it',
                        'Tests\Feature\MonetiseTest::test_rejected_payment_leaves_invoice_owing_and_allows_re_payment',
                        'Tests\Feature\MonetiseTest::test_bank_payment_requires_proof_of_payment_when_configured',
                        'Tests\Feature\MonetiseTest::test_falling_overdue_notifies_the_tenant_and_the_property_owner_once',
                        'Tests\Feature\DzimbaAccessControlTest::test_tenant_can_pay_own_due_invoice_and_download_issued_receipt',
                    ],
                ],
                [
                    'id' => 'maintenance',
                    'title' => 'Report a repair',
                    'summary' => 'Maintenance requests are available once you have an active lease.',
                    'steps' => [
                        'Open **Maintenance** and create a request: choose the property, category and priority and describe the problem.',
                        'Your owner is notified and can assign a verified contractor.',
                        'When the contractor marks the job complete, confirm the fix. The owner then closes the request.',
                    ],
                    'notes' => [
                        'Emergency requests alert your owner and ZimRent staff immediately.',
                        'Every request gets a reference number and a target response time. Requests that miss it are escalated to ZimRent staff.',
                    ],
                    'links' => [
                        ['label' => 'Open maintenance', 'route' => 'tenant.maintenance.index'],
                    ],
                    'verified_by' => [
                        'Tests\Feature\OperateTest::test_tenant_report_creates_request_with_unique_mr_number_and_sla',
                        'Tests\Feature\OperateTest::test_only_tenant_with_active_lease_can_report',
                        'Tests\Feature\OperateTest::test_emergency_report_notifies_owner_and_staff_immediately',
                        'Tests\Feature\OperateTest::test_tenant_confirms_the_fix_once_then_owner_closes',
                        'Tests\Feature\OperateTest::test_escalate_command_breaches_past_sla_requests_once_and_pages_staff',
                    ],
                ],
            ],
        ],
        [
            'id' => 'safety',
            'title' => 'Safety and notifications',
            'articles' => [
                [
                    'id' => 'report-listing',
                    'title' => 'Report a suspicious listing',
                    'summary' => 'Anyone can report a listing, even without an account. Our moderation team reviews every report.',
                    'steps' => [
                        'On the property page, open the report option.',
                        'Pick a reason and explain what happened, then submit.',
                        'If you were signed in, follow the outcome in **My Reports**.',
                    ],
                    'notes' => [
                        'Never send money or documents before viewing the property and meeting the owner.',
                    ],
                    'links' => [
                        ['label' => 'Open my reports', 'route' => 'tenant.reports.index'],
                    ],
                    'verified_by' => [
                        'Tests\Feature\TrustGovernTest::test_guest_can_report_a_listing_publicly',
                        'Tests\Feature\TrustGovernTest::test_a_report_requires_category_description_and_subject',
                        'Tests\Feature\TrustGovernTest::test_tenant_reports_page_lists_only_own_reports',
                    ],
                ],
                [
                    'id' => 'notifications',
                    'title' => 'Stay on top of notifications',
                    'steps' => [
                        'The bell at the top of every dashboard page shows your latest notifications and how many are unread.',
                        'Select a notification to mark it as read and jump to the related page.',
                        'Open **View all notifications** to see your full history or mark everything as read.',
                    ],
                    'links' => [
                        ['label' => 'Open notifications', 'route' => 'notifications.index'],
                    ],
                    'verified_by' => [
                        'Tests\Feature\EngageTest::test_notifications_page_lists_own_with_unread_count',
                        'Tests\Feature\EngageTest::test_read_marks_read_and_deep_links',
                        'Tests\Feature\EngageTest::test_read_all_marks_everything_read',
                    ],
                ],
            ],
        ],
    ],
];
