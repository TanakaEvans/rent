# Module 23 - Implementation Plan (Build Order, Party Impact, Coupling, Testing)

> The authoritative build plan for Dzimba. It answers three questions: **which module is built first**, **who in the system is impacted and related to each module**, and **which modules are so tightly coupled they must be built together**. Every module is built **end to end** and **tested after every single feature**.

## 1. Build Philosophy (non-negotiable)

1. **Vertical slices, end to end.** A module is never developed "top to bottom in one sweep" and it is never left half-built. Each feature within a module is cut from **database → model → service/controller → route → Inertia page → shared component → tests** in one sitting.
2. **Coupled modules ship together.** Modules that share state, a shared pattern, or a continuous workflow are developed inside the same build wave. Splitting them produces broken intermediate systems and expensive rework.
3. **Test after every feature, not after the module.** The moment a feature is functional, its tests exist. A module is never marked complete with untested features.
4. **No loose ends.** Dead routes, orphaned pages, TODO markers, unused imports, unguarded routes and unseeded demo data are failures, not backlog items.
5. **One city first.** Everything is built for Harare residential rentals first. Multi-city and commercial roll out in later phases.

## 1.5 Current Status & Next Up (MANDATORY - update after every feature)

> **Rule: after every finished feature, this section is updated in the same sitting. This document is the single source of truth for "what is built, what is in flight, what is next".** A feature or wave is NOT done until the tracker below is current.

| Field | Value |
|---|---|
| Current wave | Wave 3 - Commit (M7 + M8 + M20) |
| Wave status | IN PROGRESS |
| Completed slices | **Enquiry create/inbox (M5) - DONE**: `enquiries` table (message ≤1000, optional phone, reply, replied_at, read_at, status new/read/replied/closed + explicit transitions) → `EnquiryService` (create = available-only + open-thread anti-duplicate, method open/read, reply, close, inbox grouped by property, tenant list) → `EnquiryController` + 6 routes (`owner.enquiries.{index,show,reply,close}`, `tenant.enquiries.{index,store}`) → Owner inbox (grouped by property, to-count, new-highlight) + thread page (reply box, close, tenant/property context) + Tenant threads page + enquiry form on public detail page (tenant-only, guest→login) + nav/dashboard entries for both roles → seeded demo enquiry. Tests: create/validation/duplicate-block/404-unavailable; owner inbox isolation; status transitions new→read (on open) & replied (on reply) & closed; reply/close on closed blocked; tenant isolation (15 tests, `EngageTest`), access matrix extended (guest→login, tenant/owner 403s). |
| Completed slices | **Viewing slots (M6) - Wave 2 slice 2 DONE**: `viewing_slots` table (property_id FK, owner_id FK, starts_at/ends_at nullable-timestamp, status available/taken) → `ViewingSlot` model + `ViewingSlotService` (slotsFor with owner guard, create/update/destroy each authorize owner + slot↔property match, 404 on foreign rows) → `ViewingSlotController` + 4 routes (`owner.viewing-slots.{index,store,update,destroy}`) validated `starts_at after:now`, `ends_at after:starts_at` → Owner/ViewingSlots/Index page (create form, slot cards with is_past expiry grey-out, inline edit, delete; entry button on owner property show) → 3 seeded demo slots on first listed property → `StatusBadge` `expired`/`taken` tones. Tests: create/validation (past start, ends-before-start)/update/delete, owner isolation 404 (index+create), page lists only this property's slots; access matrix extended (guest→login, tenant 403, cross-owner mixin). |
| Completed slices | **Viewing requests (M6) - Wave 2 slice 3 DONE**: `viewing_requests` table (property_id, tenant_id, slot_id restricted-delete, request_message ≤1000, status requested/accepted/rescheduled/declined/completed/cancelled/no-show, outcome) → `ViewingRequest` state machine (`TRANSITIONS` per status, `TERMINAL` set) → `ViewingRequestService` (create guard: available property 404, slot-on-property, bookable slot, per-tenant open-request dedupe; accept locks slot→taken + double-book 409; decline; reschedule→rescheduled awaits tenant confirm; confirm locks new slot; cancel by either party releases a locked slot; complete; no-show releases slot; owner isolation 404 + transition guard 409) → `ViewingRequestController` + 11 routes (`owner.viewings.{index,accept,decline,reschedule,complete,no-show,cancel}`, `tenant.viewings.{index,store,confirm,cancel}`) → public detail page shows available slots to tenants with a Book a Viewing form → `Owner/Viewings/Index` (accept/decline, propose-another-slot picker, complete/no-show/cancel) + `Tenant/Viewings` (confirm rescheduled, cancel) → both navs + owner dashboard Pending Viewings stat + seeded demo request → StatusBadge requested/accepted/rescheduled/declined/completed/no-show tones. Tests: create/validation/409s (taken slot, double-book, terminal transition), accept locks, decline, reschedule→confirm, cancel releases slot, no-show/complete, full isolation (owner×owner, tenant×tenant), owner page lists only own (18 new, `EngageTest`), access matrix extended. |
| Completed slices | **Notifications in-app (M15 start) - Wave 2 slice 4 DONE** (WAVE 2 COMPLETE): framework `notifications` table added via `2026_09_09_000010` (uuid id, type, morphs notifiable, data json, read_at) → 5 notification classes (`NewEnquiryNotification`, `EnquiryRepliedNotification`, `ViewingRequestedNotification`, `ViewingAcceptedNotification`, `ViewingRescheduledNotification`) each `via=['database']` with `{title, body, link}` deep-links (enquiry→owner thread/show, viewing→party list pages) → fired inside `EnquiryService` (create→owner, reply→tenant) and `ViewingRequestService` (create→owner, accept→tenant, reschedule→tenant; confirm/cancel/decline deliberately silent) → `NotificationController` (index/read/readAll; read verifies `notifiable_id`=user, marks read, redirects to payload link) + 3 routes in the app-wide auth group (`notifications.index/read/read-all`) → shared props `notifications` (latest 8) + `unreadNotificationsCount` → header Bell with unread badge + dropdown (click→read+deep-link, View all) → `Notifications/Index` page (facade, read actions, mark-all-read) → seeded demo notifications (enquiry + viewing request, with data titles). Tests: each event raises the right row to the right recipient (Notification::fake assertions), unread count + payload title, read→deep-link redirect + read_at set, 404 on another user's notification, read-all clears, guest→login; access matrix (any authenticated role ok, guest redirects). |
| Completed slices | **Rental Applications (M7) - Wave 3 slice 1 DONE**: `rental_applications` expanded (`2026_09_09_000011` adds `reject_reason` varchar(1000) + `reviewed_by` FK — columns awaited from the Phase-2 spec) → `RentalApplication` state machine (`TRANSITIONS`: pending↔shortlisted, pending/shortlisted→approved/rejected; approved/rejected terminal) → 3 notification classes (`NewApplicationNotification`→owner on apply, `ApplicationApprovedNotification`/`ApplicationRejectedNotification`→tenant on decision) → `ApplicationService` (create: available-only + one-active-per-tenant-per-property dedupe, re-apply allowed after rejection; approve: sole-approved-per-property exclusivity, sets reviewed_by, **property status stays available — handover deferred to the lease slice**; toggleShortlist; reject: reason required + recorded) → `ApplicationController` + 6 routes (`owner.applications.{index,approve,shortlist,reject}`, `tenant.applications.{index,store}`) → public detail page Apply section (tenant: form or active-application state badge; guest→login) → `Tenant/Applications` (status prose, reject note, view listing) + `Owner/Applications/Index` (grouped by property, inline shortlist/approve/reject-with-reason) + both navs → seeded demo application notifies the owner. Tests (new `CommitTest`, 16): apply/validation/dedupe/re-apply-after-reject/404-unavailable, owner-notified, tenant page own-only, owner page grouped+scoped, approve-recorded-and-inert, cross-owner 404, one-approved-per-property, terminal immutability (approve-then-reject, reject-then-shortlist), shortlist toggle, reject reason required/recorded + tenant notified, guest redirect; access matrix extended (owner review ok / tenant 403, tenant apply ok / owner 403, guest→login). Full suite 138 passed. |
| Completed slices | **Lease create (M8) - Wave 3 slice 2 DONE**: `leases` + `lease_history` tables (`2026_09_09_000012`) → `Lease` state machine (draft→sent→signed→active→renewed→terminated, terminated terminal) + `LeaseHistory` audit + `Property::leases()` → `LeaseService` (`createFromApplication`: **approved-only** guard, one-lease-per-application/property, prefills tenant/property/rent/deposit from the application, unique `lease_no` (`LSE-<year>-NNNN`), default monthly payment terms, `clause_version 1`, history row `lease_created`, **property → `reserved` via PropertyService + history**, **auto-rejects remaining active applicants with "A lease has been created for another applicant." + notification** (the FR-05 handover deferred from slice 1), notifies tenant `LeaseCreatedNotification`; `listForOwner` scoped via `whereHas` property; `listForTenant`) → `LeaseController` + 3 routes (`owner.applications.lease` POST, `owner.leases.index`, `tenant.leases.index`) → `Owner/Leases/Index` (tenant, term, money terms, clause, status badge) + `Tenant/Leases` (status prose per state, owner/term/money/location cards) + "Create lease" CTA on the approved application card (hidden once `leases_count > 0`) + nav entries for both roles + StatusBadge `sent/signed/renewed/terminated` tones → seeded demo lease (approved application + draft `LSE-DEMO-2026-001` on the townhouse, direct rows per seeder convention — no property mutation/none of the service side-effects so the demo data stays test-stable). Tests (CommitTest extends to 25): pipeline apply→approve→lease→reserved in ONE test with prefilled amounts/history/notification, approved-only, no double lease, cross-owner 404, auto-reject of rivals + notifications, end>start validation, owner list scoping, tenant list scoping, guest redirect; access matrix +5 (owner ok / tenant 403 / owner 403 on tenant / guests). Full suite 152 passed. |
| **NEXT UP** | **Lease signing (M8) - Wave 3 slice 3**: owner sends the draft lease (`draft → sent`) → tenant and owner each sign (`lease_signatures` rows: lease_id, user_id, signed_at, signature_payload) → when both have signed the lease becomes `signed` → `active` and the property moves `reserved → occupied`. Two-party signing only moves the lease once both sign (§ workflow 2). See `docs/modules/08-lease-agreements.md` + §3 Wave 3 below. |
| In flight | (none) |
| Blocked by | (none) |
| Last updated | 2026-09-09 |

Wave 2 (Engage) is COMPLETE; Wave 3 (Commit) slices 1 (Applications) and 2 (Lease create) are DONE. Next: slice 3 (Lease signing, M8).

## 2. Definitive Build Order of the Major Modules

Every major module has a rank. Lower rank = built earlier. The **Wave** column shows what it ships with; it never ships alone when it is part of a coupled group.

| Rank | Module | Wave | Must be built together with | Why this order |
|---|---|---|---|---|
| 1 | **User & Account (M1)** | Wave 0 (DONE) | Roles, middleware, layouts, navigation | Every other module needs an authenticated, correctly role-gated user. |
| 2 | **Property Management (M2)** | Wave 1 | **Marketplace (M3), Favourites (M4)** | The owner cannot add value until listings exist; the tenant cannot discover without the marketplace; favourites are meaningless without listings. **This is the content engine. Build it first.** |
| 3 | **Marketplace / Search (M3)** | Wave 1 | **M2, M4** | Consumes M2 only; without it M2 has no outward face. |
| 4 | **Favourites & Saved Searches (M4)** | Wave 1 | **M2, M3** | Smallest module; depends on both. Saved-search alerts can split to Wave 5 (needs Notifications). |
| 5 | **Enquiries & Communication (M5)** | Wave 2 | **Viewings (M6)** | Both are "tenant initiates → owner responds" conversational flows sharing threads, statuses and notifications. Building the pattern once saves a full module of rework. |
| 6 | **Property Viewings (M6)** | Wave 2 | **M5** | Scheduling requests ride on the communication/notification pattern established by M5. |
| 7 | **Rental Applications (M7)** | Wave 3 | **Leases (M8), Documents-lite (M20)** | Application **approval is meaningless until it becomes a lease**. The pipeline tenant-applies → owner-approves → lease-signs → property-occupied is one unbroken vertical. |
| 8 | **Lease & Agreements (M8)** | Wave 3 | **M7, M20** | Cannot exist without an approved application; produces the documents that M20 must store. |
| 9 | **Documents (M20)** | Wave 3 | **M7, M8 (start), reused everywhere after** | Only the lease-signing store is built in Wave 3; the full repository grows with later waves. |
| 10 | **Subscriptions (M12)** | Wave 4 | **Rent & Payments (M9), Featured (M13)** | **All three monetise through one shared payment gateway and one billing/receipt pattern.** Quota enforcement also hooks the M2 publish action. |
| 11 | **Rent & Payments (M9)** | Wave 4 | **M12, M13** | Provides the gateway + ledger that subscriptions and ad placements reuse. |
| 12 | **Featured & Advertising (M13)** | Wave 4 | **M9, M12** | Buys placements that M3 renders; pays through the M9 gateway. |
| 13 | **Maintenance (M10)** | Wave 5 | **Contractors (M11)** | A maintenance job that cannot be assigned to a verified tradesperson is half a module. Ratings only make sense against completed jobs. |
| 14 | **Contractors (M11)** | Wave 5 | **M10** | Only becomes useful once there are jobs to assign. |
| 15 | **Verification (M14)** | Wave 6 | **Disputes (M19), Admin completion (M18), Reports (M17)** | Verification badges and dispute resolution are both admin-trust functions sharing the evidence/document store and the admin queue; reports make them measurable. |
| 16 | **Complaints & Disputes (M19)** | Wave 6 | **M14, M18** | Needs the verification state (badge holds) and evidence from earlier waves. |
| 17 | **Admin / System Management (M18)** | Wave 6 | **M14, M19, M17** | The console is completed last because by then every admin surface exists to be wired in. |
| 18 | **Reports & Analytics (M17)** | Wave 6 | **M14, M18** | No meaningful report can be validated before ledgers and cases exist. |
| - | **Notifications (M15)** | Cross-cutting | Starts in-app in Wave 1; email/SMS/WhatsApp grow each wave | Every wave raises events. Start the bell in Wave 1, add channels when each workflow needs them. |
| - | **Landlord Dashboard (M16)** | Cross-cutting | Progressively enriched every wave | Starts with property/application cards (DONE); gains rent, maintenance and analytics cards as those modules land. |

### What this means for "start development"

The **first major modules to be developed** are **Property Management (M2) + Marketplace (M3) + Favourites (M4) in Wave 1** - because they are the only wave that produces a complete, testable business loop (owner lists → tenant finds → tenant saves) with zero dependence on unfinished work. Nothing else can start before it, and nothing in it depends on later modules.

## 3. Party Impact & Relationship Map

For every major module the three parties have a different role: **Driver** (the one the feature exists for), **Responder** (opposite side of the transaction), **Governor/Monitor** (admin oversight). "Impact" = the party whose data, dashboard or process changes.

| Module | Impact on Admin | Impact on Owner | Impact on Tenant | Has Driver→Responder pair |
|---|---|---|---|---|
| M1 Users & Accounts | Driver (creates/audits) | uses (profile/security) | uses (profile/security) | n/a (platform) |
| M2 Properties | Governor (moderate/verify) | **Driver (list & manage)** | views (cards, detail) | Owner → Tenant |
| M3 Marketplace | Governor (config/moderation) | benefits (visibility) | **Driver (search & filter)** | Tenant ← Owner |
| M4 Favourites | - | benefits (save signals) | **Driver (save/list)** | Tenant only |
| M5 Enquiries | Monitor (audit/abuse) | Responder (reply) | **Driver (ask)** | **Tenant → Owner** |
| M6 Viewings | Monitor (SLA/abuse) | Responder (schedule/accept) | **Driver (request)** | **Tenant → Owner** |
| M7 Applications | Governor (oversight) | **Driver (review/approve)** | Responder (apply/track) | Tenant → **Owner** |
| M8 Leases | Governor (templates/oversight) | **Driver (create/sign)** | Responder (review/sign) | Owner → Tenant |
| M9 Rent & Payments | Governor (gateway/payouts) | Driver (collect/income) | Responder (pay/balance) | Owner → Tenant |
| M10 Maintenance | Governor (SLA/escalation) | Driver (approve/assign/close) | Responder (report) | Tenant → **Owner** |
| M11 Contractors | Governor (registry/ratings) | Driver (hire/assign) | Monitor (job done) | Owner → Contractor* |
| M12 Subscriptions | **Driver (plans/billing)** | Responder (pay/subscribe) | - | Admin → Owner |
| M13 Featured/Ads | **Driver (pricing/approve)** | Responder (buy) | sees badges | Admin → Owner → Tenant |
| M14 Verification | **Driver (verify)** | Responder (submit evidence) | trusts (badges) | Admin ↔ Owner |
| M15 Notifications | configures/globals | receives | receives | all |
| M16 Landlord Dashboard | - | **Driver (operate)** | - | Owner only |
| M17 Reports | **Driver (platform)** | Driver (own portfolio) | light (own activity) | Admin + Owner |
| M18 Admin Console | **Driver (everything)** | data source | data source | Admin |
| M19 Disputes | **Driver (resolve)** | Responder (defend) | Responder (report/appeal) | any → Admin |
| M20 Documents | **Driver (registry/retention)** | uploader | uploader | Admin + all |

\* M11 introduces a **fourth actor** (Contractor). Flag it in the UI narration: Contractor is not a platform subscriber; they are registered users whose scope is `contractor` workflows only.

### Coupling rationale for "must be built at once" groups

| Coupled group | Reason they cannot be split |
|---|---|
| M2 + M3 + M4 | One continuous content pipeline. Marketplace needs seeded, real listings to test filtering/cards; favourites need cards to attach to. Splitting leaves M3 and M4 impossible to test. |
| M5 + M6 | Identical shared scaffold (thread + statuses + notifications + row-level isolation per owner). Duplicating it later is the single most wasteful rework in the plan. |
| M7 + M8 + M20 | A single acceptance path: apply → approve → lease → signed → occupied → document stored. Stopping at "approval" leaves dead state with no next step. |
| M9 + M12 + M13 | One billing brain. Gateway config, receipt numbering, PII-safe payment records and dispute hooks are shared. Rent, subscription and ad money all reconcile from one ledger. |
| M10 + M11 | Maintenance without an assignee is a to-do list, not a workflow. |
| M14 + M19 + M18 + M17 | Trust layer. Badge holds are a dispute outcome; dispute reporting needs the evidence store; both need the admin queue; their effectiveness must be measurable. |

## 4. Wave-by-Wave Vertical Slices

Each wave: scope, end-to-end walk, and the exact feature tests required. **Finish = all steps done + all tests green + no loose ends.**

### Wave 0 - Foundation (DONE)

Auth by email/username, lockout, password expiry/forced change, roles + pivots, `admin`/`role` middleware + 403, role-redirect login, Dzimba layouts, marketplace shell, Admin/Owner/Tenant dashboards, `properties` + `property_favourites` + `rental_applications` tables, access test suite.

### Wave 1 - List & Discover (M2 + M3 + M4)

**End-to-end walk:** owner creates a listing (migration already exists; add gallery upload) → listing validates and publishes → marketplace lists it with filters working → tenant opens detail → tenant saves favourite → owner dashboard shows the saved-count and latest listings.

| Feature | End-to-end steps | Tests required per feature |
|---|---|---|
| Property CRUD (Owner) | DB (gallery/images table; zone + building_size + land_size on properties) → model → `PropertyService` + controller + validation → routes under `role:Owner` → Owner pages → StatusBadge reuse | create; update; delete; only-own-properties 403/404 for other owners; validation errors |
| Listing publish & status | status transitions (available/reserved/occupied/unavailable) with history | transition allowed matrix; reserved/occupied blocked for for others; history row written |
| Marketplace search | index query + filters + sort + pagination → public page + cards (card anatomy per `docs/modules/03` market review) | filter combos (type, zone/city, price range min+max, beds, baths, furnished); only `available` shown; featured-first sort; pagination bounds |
| Property detail page | single-property read → gallery, amenities, owner info, CTAs | 200 for public; 404 for hidden/unavailable; correct owner info |
| Favourites (M4) | pivot CRUD → toggle on card + detail + "My Favourites" page | toggle on/off idempotent; unique constraint; list correct; guest→login redirect |

**Wave-tests gate:** `php artisan test` (new suite `ListingAndDiscoverTest`) + route:list has only live routes + no dead imports/build-clean.

### Wave 2 - Engage (M5 + M6) ✅ COMPLETE

**End-to-end walk:** tenant enqueues → owner inbox shows grouped by property → owner replies → tenant sees thread → tenant requests viewing → owner accepts → slot locked → both parties see confirmations. Notifications (in-app) raised on enquiry created/replied and viewing requested/accepted/rescheduled.

| Feature | End-to-end steps | Tests required per feature |
|---|---|---|
| Enquiry create/inbox | ✅ enquiry table → service + validation → routes → owner inbox + tenant thread | ✅ create; owner-only inbox isolation; status transitions new/read/replied/closed; reply append |
| Viewing slots | ✅ slots CRUD (owner) | ✅ owner creates/edits slots; slot date validation; owner/slot isolation 404 |
| Viewing requests | ✅ request on slot → accept/reschedule/cancel → reminders flagged | ✅ accept locks slot (double-book 409); reschedule path; cancel path; no-show marking; isolation 404s |
| Notifications in-app (M15 start) | ✅ bell + `notifications` rows on enquiry/viewing events, read/unread + deep-link | ✅ event creates row; read/unread; deep-link; isolation |

**Wave-tests gate:** `EngageTest` suite green; access tests extended for `/owner` inbox and `/tenant` threads; full suite green.

### Wave 3 - Commit (M7 + M8 + M20)

**End-to-end walk:** tenant applies → owner shortlists → owner approves → property `reserved` → other applications auto-rejected → owner generates lease from application → both sign → lease `active` + property `occupied` → lease document in store.

| Feature | End-to-end steps | Tests required per feature |
|---|---|---|
| Apply (M7) | ✅ application create + validation + tenant tracking page | ✅ double-apply blocked; property-must-be-available; message length |
| Review/approve/reject | ✅ owner review screen; approve/reject/shortlist | ✅ exclusivity (one approved); reject reason recorded; terminal immutability; isolation 404s — ⏳ auto-reject others + status→reserved deferred to lease slice |
| Lease create (M8) | generate from approved application → lease_no → draft | only-from-approved-application; data prefilled correctly |
| Signing (M8) | two-party signature flow → active | signed only after both; state active; property → occupied |
| Document store (M20-lite) | store signed lease as `documents` row + version | file stored; accessible to both parties; admin-only access 403 for tenant on others' docs |

**Wave-tests gate:** `CommitTest` suite green; the pipeline test (apply→lease→occupied) asserted in ONE feature test; full suite green.

### Wave 4 - Monetise (M9 + M12 + M13)

**End-to-end walk:** owner subscribes (quota enforced on publish from Wave 1) → rent schedule auto-generated from active lease → invoice issued → tenant pays → invoice `paid` + receipt → arrears computed → owner buys featured placement → placement window promotes listing.

| Feature | End-to-end steps | Tests required per feature |
|---|---|---|
| Plans/subscribe (M12) | plans CRUD (admin) + subscribe + quota hook | quota enforced at publish; pro-rata upgrade; grace→suspend |
| Rent schedule (M9) | generation from active lease → invoices → reminders | exact invoice count for term; amounts match lease; period boundaries |
| Payment (M9) | gateway/bank-mobile manual entry → paid + receipt no | idempotency (no double-pay); receipt unique; overdue calc |
| Arrears & income | aggregation service → landlord dashboard | arithmetic exactness vs ledger fixture |
| Ads (M13) | package purchase → placement window → featured sort in M3 | window enforcement; one-placement rule; prorated refund on cancel |

**Wave-tests gate:** `MonetiseTest` suite green; money exactness tests (decimal, no float); full suite.

### Wave 5 - Operate (M10 + M11)

**End-to-end walk:** tenant reports leak → owner approves + quotes → contractor assigned → contractor works (status in_progress) → completed → owner inspects + closes → rated.

| Feature | End-to-end steps | Tests required per feature |
|---|---|---|
| Report & triage (M10) | request create + priority + SLA counter | emergency notifies admin; priority escalation timer |
| Assignment (M11) | contractor registry + assign to job | only verified assignable; double-assign blocked |
| Track & close | status transitions + tenant confirm → closed | transition matrix; closed feeds maintenance spend |
| Ratings | post-completion rating → recompute | rating-only-after-completed; average correctness |

**Wave-tests gate:** `OperateTest` suite green; full suite.

### Wave 6 - Trust & Govern (M14 + M19 + M18 + M17)

**End-to-end walk:** owner submits evidence → admin verifies → badge → tenant filters verified-only → a listing gets reported → badge holds → admin investigates → resolves → report is measurable in platform analytics.

| Feature | End-to-end steps | Tests required per feature |
|---|---|---|
| Verification (M14) | case + evidence docs + badge state + verified-only filter | badge reflects latest decision; evidence never public; revoke demotes |
| Disputes (M19) | report/dispute create → evidence attach → admin decision + action log | full action history; badge hold on report; restore on dismissal |
| Admin console completion (M18) | queues for verify/dispute/review + audit | admin-role matrix 403s; audit rows written |
| Reports (M17) | platform + owner aggregates + export | reconciles with ledgers; owner isolation; CSV export shape |

**Wave-tests gate:** `TrustGovernTest` suite green; full suite; access matrix fully covered.

## 5. Test-After-Every-Feature Protocol (MANDATORY)

Every feature, in this order, before it counts as done:

1. **Write the feature test first** if behaviour is already clear (Test-last accepted when the slice is exploratory, but the test is still written in the same sitting).
2. **Happy path test** - the main success journey.
3. **Negative test** - the guard (e.g. wrong role → 403, wrong owner → 404, invalid input → 422).
4. **State-transition / money test** - whenever the feature changes a status or an amount (exclusivity, idempotency, exactness).
5. **Permission-matrix test** - the new route appears in the role matrix suite so it can never be forgotten later.
6. **Run the feature suite**: `php artisan test --filter=<SuiteName>`.
7. **Run the full suite**: `php artisan test`.
8. **Front-end changed?** `npm run build` must pass with no unused-import warnings; typecheck clean.
9. **Routes changed?** `php artisan route:list` - confirm no duplicate/orphan route names.
10. **DB changed?** `php artisan migrate:fresh --seed` on the dev DB, then re-run the full suite.

**Definition of "No loose ends" (every feature and every wave):**

- No route without a controller, no controller without a route.
- No page added to navigation that 403s for its intended role.
- No TODO/FIXME/TBD marker left.
- No commented-out code, dead imports, unused props or orphaned components.
- No new role-gated route absent from the access-control tests.
- Demo seed data updated if a feature changes.

## 6. Sequencing Guardrails

1. **Never start Wave N+1 while Wave N has open tests.** Untested code freezes the wave.
2. **Coupling over convenience.** M5 and M6 are built together even though "enquiries" alone would ship faster; the second flow would cost more later.
3. **The marketplace is the proof point.** Waves are only signed off when demoable in the browser for all three parties, not when the backend passes tests.
4. **M15 and M16 grow with every wave.** Do not build them "fully" in Wave 1; extend them as each new event type and data source lands.
5. **Estimates (indicative, single developer):** Wave 1 ≈ 3-4 weeks, Wave 2 ≈ 2-3 weeks, Wave 3 ≈ 3-4 weeks, Wave 4 ≈ 4-5 weeks, Wave 5 ≈ 2-3 weeks, Wave 6 ≈ 2-3 weeks. These are stretch targets and each wave's gates are the source of truth.

## 7. Definition of Done (every module, unchanged)

1. Migrations + models + relationships.
2. Seed data where needed.
3. Controllers + route group scoped by role.
4. Inertia page + shared components (StatusBadge etc.).
5. Feature tests: happy path + negative (403) + permission matrix.
6. Module doc in `docs/modules/` updated and cross-referenced.
7. `docs/modules/23-implementation-plan.md` **§1.5 status tracker updated** (next-up feature ticked off, next feature promoted) and wave status ticked.
8. AGENTS.md global rules satisfied (no loose ends, standards checked, tests run).