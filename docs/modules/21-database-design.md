# Module 21 - Database Design & Relationships

> Covers implemented tables and the planned expansion. Current engine: MySQL (local dev), SQLite (tests).

## 21.1 Conventions

- Primary keys: `id BIGINT UNSIGNED AUTO_INCREMENT`.
- Foreign keys: `{singular}_id` and `constrained()` to the target table.
- Monetary columns: `DECIMAL(12,2)`.
- Soft-fragile data (money, status) is never computed float.
- Timestamps: `created_at`, `updated_at` on all business tables.
- All changes nullable-enabled for historical tables.

## 21.2 Entity-Relationship (text ERD)

```
auth_roles ───< auth_user_roles >─── auth_users ───< auth_login_logs
                                    │   │    │
                  ┌─────────────────┘   │    └──────────────┐
                  ▼                     ▼                   ▼
            companies ─< branches ─< departments ─< sections
                  │                    │                    │
                  │                    │                    │
                  ▼                    ▼                    ▼
                  └──────────     employees  <──────────────┘
                                │
                            (user_id FK)

auth_users (owner) ───< properties >───< property_favourites >─── auth_users (tenant)
                         │       │
                         │       └────────< rental_applications >─── auth_users (tenant)
                         │                └─ status pending/shortlisted/approved/rejected
                         │
                         ├──< property_images       (Wave 1)
                         ├──< property_history       (Wave 1) - status-change audit
                         ├──< property_documents     (Phase 2, via documents)
                         ├──< viewing_slots          (IMPLEMENTED, Wave 2)
                         ├──< viewing_requests       (IMPLEMENTED, Wave 2)
                         ├──< enquiries              (IMPLEMENTED, Wave 2)
                         ├──< leases                 (IMPLEMENTED, Wave 3) ─< lease_history ─< lease_signatures (slice 3); renewals self-link ─`renewed_from` (slice 4) ─< documents (slice 5, M20-lite)
                         ├──< rent_schedules         (IMPLEMENTED, Wave 4) ─< rent_invoices (slice 3) ─< payments (slice 4)
                         └──< maintenance_requests   (Phase 2) ─< maintenance_actions

auth_users ─< notifications (IMPLEMENTED, Wave 2 - morphed)

auth_users (owner) ─< subscriptions >── subscription_plans ─< subscription_plan_features >── subscription_features
auth_users (owner) ─< ad_placements >── ad_packages ─< ad_events
verification_cases (polymorphic subject) ─< verification_documents
reports / disputes (polymorphic) ─< case_actions
documents ─< document_versions (polymorphic links)
role_routes ─ system_routes ─ system_modules
system_configurations ─< configuration_audits (config change history; drives billing/approval/rule engines)
```

## 21.3 Implemented Tables

### auth_users
| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED PK | |
| name | VARCHAR(150) | |
| email | VARCHAR(150) | unique |
| username | VARCHAR(100) unique | login id |
| email_verified_at | TIMESTAMP NULL | |
| password | VARCHAR(255) | bcrypt |
| status | ENUM(active,inactive) | default active |
| remember_token | VARCHAR(100) NULL | |
| password_changed_at | TIMESTAMP NULL | forced-change control |
| password_expires_at | TIMESTAMP NULL | expiry policy |
| failed_login_attempts | INT | default 0 |
| locked_at | TIMESTAMP NULL | lockout |
| created_at/updated_at | TIMESTAMP | |

Indexes: `(email, status)`, `(username, status)`.

### auth_roles
| Column | Type |
|---|---|
| id | BIGINT UNSIGNED PK |
| name | VARCHAR(100) unique |
| description | TEXT NULL |
| timestamps | |

### auth_user_roles
role_id FK → auth_roles, user_id FK → auth_users, assigned_by FK → auth_users (nullable, set null on delete).
Unique `(user_id, role_id)`.

### auth_login_logs
user_id FK → auth_users (cascade), ip_address VARCHAR(45), user_agent TEXT, login_at, logout_at.
Indexes `(user_id, login_at)`, `(login_at)`.

### properties (Dzimba core)
| Column | Type |
|---|---|
| id | BIGINT UNSIGNED PK |
| owner_id | FK → auth_users (cascade) |
| title | VARCHAR(200) |
| description | TEXT NULL |
| property_type | ENUM(house,flat,townhouse,cottage,room,commercial,land) default house |
| bedrooms | TINYINT UNSIGNED default 1 |
| bathrooms | TINYINT UNSIGNED default 1 |
| building_size | DECIMAL(9,2) NULL | m² |
| land_size | DECIMAL(12,2) NULL | m² (or acres for land plots, stored as m²) |
| price | DECIMAL(12,2) |
| deposit | DECIMAL(12,2) NULL |
| furnished | BOOLEAN default false |
| status | ENUM(available,reserved,occupied,unavailable) default available |
| suburb | VARCHAR(100) NULL |
| zone | VARCHAR(100) NULL | city zone, e.g. "Harare North" |
| city | VARCHAR(100) NULL |
| address | VARCHAR(255) NULL |
| amenities | JSON NULL |
| cover_image | VARCHAR(255) NULL |
| featured | BOOLEAN default false |
| verified | BOOLEAN default false |
| available_from | DATE NULL |
| timestamps | |

Indexes: `(status, city)`, `(owner_id, status)`, and `(status, price)` for the marketplace price range.

### property_favourites (Dzimba core)
user_id FK → auth_users (cascade), property_id FK → properties (cascade). Unique `(user_id, property_id)`.

### rental_applications (IMPLEMENTED, Wave 3)
property_id FK → properties (cascade), applicant_id FK → auth_users (cascade), message VARCHAR(1000) NULL, status ENUM(pending,shortlisted,approved,rejected) default pending, **reject_reason VARCHAR(1000) NULL (Wave 3 slice 1)**, **reviewed_by FK → auth_users NULL (Wave 3 slice 1)**.
Indexes: `(property_id, status)`, `(applicant_id, status)`. App-layer state machine: pending↔shortlisted; pending/shortlisted → approved/rejected; approved/rejected terminal; one approved per property (approval inert until the lease slice moves the property).

### property_images (Wave 1)
id, property_id FK → properties (cascade), path, caption, sort_order SMALLINT.

### property_history (Wave 1 - status-change audit)
id, property_id FK → properties (cascade), from_status ENUM(available,reserved,occupied,unavailable), to_status ENUM(available,reserved,occupied,unavailable), changed_by FK → auth_users (nullOnDelete), timestamps. Index `(property_id, created_at)`.
Writes on every allowed transition (matrix in `Property::TRANSITIONS`).

### companies / branches / departments / sections / employees (system admin)
- **companies**: id, name, contact fields, timestamps.
- **branches**: id, company_id FK → companies, name, location, status.
- **departments**: id, company_id/branch_id FK, name, head_id FK, status.
- **sections**: id, name, description, department_id FK → departments (cascade), head_id FK → employees (nullOnDelete), status.
- **employees**: id, user_id FK → users (set null), branch_id, department_id FK (set null), employee_number unique, name fields, gender, national_id, email, phone, job_title, hire/termination dates, employment_type, salary DECIMAL(10,2), bank fields, emergency contact, photo, status ENUM(active,inactive,terminated,suspended).

### system_modules / system_routes / role_routes / system_settings
- **system_modules**: id, name, prefix unique, icon, description, order, status ENUM(Enabled,Disabled).
- **system_routes**: id, system_module_id FK (cascade), name unique, uri, description, status ENUM(active,inactive).
- **role_routes**: id, role_id FK → auth_roles, system_route_id FK → system_routes (cascade); unique (role_id, system_route_id).
- **system_settings**: id, key unique, value, group.

### Framework tables
- `users` (default Laravel), `cache`, `jobs`, `job_batches`, `failed_jobs`, `sessions`, `password_reset_tokens`. (The framework `notifications` table is NOT shipped — added as a custom migration below.)

### notifications
- **notifications** (IMPLEMENTED, Wave 2): `2026_09_09_000010_create_notifications_table` — id uuid PK, type, notifiable_id + notifiable_type (morphs), data (json for title/body/link), read_at nullable, timestamps. Used by five `App\Notifications\*` classes (database channel, deep-link payload) fired from `EnquiryService` (create→owner, reply→tenant) and `ViewingRequestService` (requested→owner, accepted/rescheduled→tenant). `notification_preferences` + `notification_logs` remain Phase 2.

## 21.4 Planned Phase-2 Tables

### enquiries
- **enquiries** (IMPLEMENTED, Wave 2): id, property_id FK, tenant_id FK, message VARCHAR(1000), phone VARCHAR(20) nullable, reply VARCHAR(1000) nullable, replied_at, status ENUM(new,read,replied,closed), read_at, timestamps; indexes (property_id, tenant_id), (status).
- **enquiry_messages** (Phase 2): id, thread_id FK, sender_id FK, body TEXT, sent_at, seen_at.

### viewing_slots + viewing_requests
- **viewing_slots** (IMPLEMENTED, Wave 2): id, property_id FK, owner_id FK, starts_at timestamp nullable, ends_at timestamp nullable, status ENUM(available,taken), timestamps; index (property_id, status). `ViewingSlotService` authorizes owner + slot↔property match (404 on foreign rows); `is_past` computed (expired display, no persisted field).
- **viewing_requests** (IMPLEMENTED, Wave 2): id, property_id FK, tenant_id FK, slot_id FK (restrictOnDelete), request_message VARCHAR(1000) nullable, status ENUM(requested,accepted,rescheduled,declined,completed,cancelled,no-show), outcome, timestamps; indexes (property_id, status), (tenant_id, status). `ViewingRequestService` guards transitions per status (409) and ownership (404); accept/confirm set slot `taken`; cancel/decline/no-show release a `taken` slot back to `available`.

### leases + lease_signatures + lease_history
- **leases (IMPLEMENTED, Wave 3 slices 2-4)**: id, property_id FK, tenant_id FK, application_id FK nullable (nullOnDelete — a lease survives its application), lease_no unique, start_date/end_date nullable, rent_amount DECIMAL(12,2), deposit_amount DECIMAL(12,2), payment_terms JSON, status ENUM(draft,sent,signed,active,renewed,terminated) default draft, clause_version SMALLINT default 1, **renewed_from_id FK nullable (self-referencing leases, nullOnDelete + index — links a renewal to its original; set by `LeaseService::renew`, slice 4)**, timestamps.
- **lease_signatures (IMPLEMENTED, Wave 3 slice 3)**: id, lease_id FK (cascade), user_id FK (cascade), signed_at timestamp nullable, signature_payload VARCHAR(255) nullable, unique (lease_id, user_id), index (lease_id, signed_at).
- **lease_history (IMPLEMENTED, Wave 3 slice 2)**: id, lease_id FK (cascade), action, performed_by FK (nullOnDelete), details JSON, timestamps. Audit entries written for every state change (e.g. `lease_created`).

### documents (M20-lite)
- **documents (IMPLEMENTED, Wave 3 slice 5 — M20-lite)**: id, lease_id FK nullable (cascadeOnDelete — M20-lite links one lease agreement document; the polymorphic `document_links` table ships with full M20), type VARCHAR default `lease_agreement`, name, content LONGTEXT (plain-text agreement snapshot; binary/pdf storage ships with full M20), mime default `text/plain`, size BIGINT UNSIGNED nullable, version SMALLINT UNSIGNED default 1 (bumps in place on re-store; `document_versions` history table deferred), visibility VARCHAR default `private`, timestamps; indexes (lease_id, version), (type). `DocumentService` renders the snapshot (`renderAgreementText`), stores/versions it on lease activation (`storeLeaseAgreement`), and authorizes reads (`authorize`: admin/superuser OR lease property owner OR lease tenant, else 404).

### rent_schedules + rent_invoices + payments + deposits
- **rent_schedules (IMPLEMENTED, Wave 4 slice 3)**: id, lease_id FK (unique — one schedule per lease, cascade), start_date, end_date, rent_amount DECIMAL(12,2), payment_terms JSON, timestamps. `RentService::generateFor` creates exactly one per active lease (idempotent; inactive leases never bill). `RentSchedule::invoices()`/`outstandingInvoices()` (draft/due/overdue).
- **rent_invoices (IMPLEMENTED, Wave 4 slices 3 & 5)**: id, property_id FK (cascade), tenant_id FK (cascade), lease_id FK (cascade), schedule_id FK (cascade), period_start, period_end, amount DECIMAL(12,2), late_fee DECIMAL(12,2) default 0 (added `2026_09_09_000026`, slice 5 — recomputed deterministically by `RentService::accrueLateFees()` from `late_fees.*` config), status ENUM(draft,due,paid,overdue,cancelled,refunded) default draft, invoice_no unique (`RNT-YYYY-NNNN` from `numbering.rent_invoice.*`), reminded_at nullable, timestamps; indexes (lease_id), (tenant_id, status), (period_start, status). One per calendar month (`buildPeriods`), first/last clamped to the term; explicit state machine (`RentInvoice::STATUSES`/`TRANSITIONS`); `paid`/`refunded` settlement lands with the payment slice (Wave 4 slice 4).
- **payments (IMPLEMENTED, Wave 4 slice 4)**: id, invoice_id FK (rent_invoices, cascade), paid_by FK (auth_users, cascade), received_by FK (auth_users, nullable, nullOnDelete), amount DECIMAL(12,2), method ENUM(cash,bank,mobile,online), reference VARCHAR(255) nullable, pop_path VARCHAR(255) nullable, receipt_no VARCHAR(40) unique nullable, status ENUM(pending,settled,rejected,refunded) default pending, paid_at timestamp nullable, timestamps; indexes (invoice_id), (paid_by), (status, created_at). Explicit state machine (pending→[settled,rejected], settled→[refunded], rejected/refunded terminal) enforced in `PaymentService`; one pending/settled payment per invoice blocks re-pay (NFR-02); settlement moves the invoice to `paid` and issues the unique `RCT-` receipt; FKs target `auth_users` (the app's real user store) not `users`.
- **deposits (planned, Phase 2)**: id, lease_id FK, amount DECIMAL(12,2), status ENUM(held,returned,forfeited), deductions JSON, returned_at.

### maintenance_requests + maintenance_actions
- **maintenance_requests**: id, property_id FK, tenant_id FK, category ENUM(plumbing,electrical,appliance,structural,pest,safety,other), priority ENUM(low,medium,high,emergency), title, description, status ENUM(reported,assigned,in_progress,completed,closed,declined), approved_quote DECIMAL(12,2) NULL, assigned_contractor_id FK nullable, resolved_at, timestamps.
- **maintenance_actions**: id, request_id FK, actor_id FK, action, notes, created_at.

### contractors + contractor_trades
- **contractors**: id, user_id FK nullable, business_name, contact, service_area JSON, status ENUM(unverified,vetting,verified,suspended), rating_avg DECIMAL(3,2), jobs_completed INT.
- **contractor_trades**: id, contractor_id FK, trade, rate.

### subscription_plans + subscription_features + subscription_plan_features + subscriptions + subscription_invoices + subscription_history

- **subscription_plans (IMPLEMENTED, Wave 4 — config-driven)**: id, name, code VARCHAR nullable, description nullable, price DECIMAL(12,2), currency VARCHAR(3) default base-currency config, billing_frequency VARCHAR(20) default `monthly` (one_time/daily/weekly/monthly/quarterly/semi_annual/annual/custom), billing_interval SMALLINT default 1, trial_days SMALLINT default 0, grace_days SMALLINT nullable (null → `subscriptions.grace_period_days` config), listing_limit SMALLINT nullable (null = unlimited), featured_slots SMALLINT default 0, status VARCHAR(20) default `active` (active/archived), start_date/end_date nullable timestamps (sale window), timestamps. (Support tier + analytics boolean replaced by Feature-Catalogue grants.)
- **subscription_features (IMPLEMENTED, Wave 4)**: id, code VARCHAR(40) unique (PROPERTY_LISTING, PROPERTY_ANALYTICS, ADVANCED_SEARCH, TENANT_MESSAGING, VIEWING_MANAGEMENT, APPLICATION_MANAGEMENT, RENT_COLLECTION, MAINTENANCE, FINANCIAL_REPORTS, FEATURED_LISTINGS, MULTIPLE_USERS, MULTIPLE_BRANCHES, API_ACCESS, DEDICATED_SUPPORT), label, description nullable, status, timestamps.
- **subscription_plan_features (IMPLEMENTED, Wave 4)**: id, plan_id FK (cascade), feature_id FK (cascade), unique (plan_id, feature_id).
- **subscriptions (IMPLEMENTED, Wave 4)**: id, owner_id FK (auth_users, cascade), plan_id FK (restrict), status VARCHAR(20) default `active` (active/grace/suspended/cancelled), starts_at timestamp, ends_at timestamp nullable (cycle end), cycle VARCHAR(20) (billing_frequency snapshot), details JSON nullable (pending_downgrade_to, pending_upgrade_to, grace_until), timestamps; index (owner_id, status). `pending_upgrade_to` carries an `apply_at_renewal` upgrade to be settled by `SubscriptionService::applyCycleEnd`. Note the `billing_cycle` column on subscription_plans (Wave 3 sandbox-era snapshot) coexists with `billing_frequency`/`billing_interval` planned for M9; the seeded plans and `ensureFor`/`startFreshSubscription` write `billing_cycle`.
- **subscription_invoices (IMPLEMENTED, Wave 4)**: id, subscription_id FK (cascade), amount DECIMAL(12,2), status VARCHAR(20) default `pending` (pending/paid/cancelled/refunded), invoice_no VARCHAR unique (numbering from `numbering.*` config), receipt_no VARCHAR unique nullable, paid_at timestamp nullable, details JSON nullable, timestamps; index (status).
- **subscription_history (IMPLEMENTED, Wave 4)**: id, subscription_id FK (cascade), event VARCHAR (subscribed/renewed/upgraded/downgraded/switched/grace_period/suspended/cancelled), details JSON nullable, created_at timestamp; index (subscription_id, created_at). Single table name (no plural) — model pins `$table = 'subscription_history'`.

### system_configurations + configuration_audits (Module 24 — Configuration Engine)

- **system_configurations**: id, group_name VARCHAR(50) indexed, key VARCHAR(100) unique (`group.key`), type VARCHAR(20) (string/integer/boolean/decimal/json), value TEXT nullable, label VARCHAR, description nullable, risk VARCHAR(20) default `low` (low/medium/high/critical), is_editable BOOLEAN default true, status VARCHAR(20) default `active`, timestamps.
- **configuration_audits**: id, configuration_id FK nullable (nullOnDelete — keep history when a key is removed), key VARCHAR(100), old_value TEXT nullable, new_value TEXT nullable, changed_by FK nullable (auth_users, nullOnDelete), reason VARCHAR nullable, approved_by FK nullable, effective_from timestamp nullable, created_at. **Every change writes a row (old/new/by/at/why/approval).**

Escaping the `group` column name (a SQL keyword in some SQLite versions) is handled by naming the column `group_name`. All commercial values (grace, proration mode, suspension behaviour, numbering prefixes/padding, cycle-day map, renewal reminders, over-limit copy, payment/POP/approval, late fees, featured pricing) live here and are read via `ConfigurationService` — never hard-coded.

### ad_packages + ad_placements + ad_events (Module 13 — Featured ads, Wave 4 slice 6)
- **ad_packages (IMPLEMENTED, Wave 4 slice 6)**: id, code VARCHAR(40) unique, name VARCHAR(100), placement_type ENUM(featured,top,homepage,premium_badge) default `featured`, price DECIMAL(12,2), duration_days SMALLINT UNSIGNED default 30, description VARCHAR(255) nullable, is_active BOOLEAN default true, timestamps.
- **ad_placements (IMPLEMENTED, Wave 4 slice 6)**: id, property_id FK → properties (cascade), owner_id FK → auth_users (cascade), package_id FK nullable → ad_packages (nullOnDelete), amount DECIMAL(12,2) (price snapshot), starts_at/ends_at/paused_at/paid_at nullable timestamps, status ENUM(reserved,active,paused,expired,cancelled) default `reserved`, credit_amount DECIMAL(12,2) default 0.00 (prorated refund on cancel), admin_note VARCHAR(500) nullable, timestamps; indexes (property_id), (owner_id), (status), (status, ends_at). State machine: reserved→active→expired/cancelled; `paused` freezes the window (resume extends `ends_at`).
- **ad_events (IMPLEMENTED, Wave 4 slice 6)**: id, placement_id FK → ad_placements (cascade), event_type ENUM(impression,click,enquiry,application), created_at (useCurrent, no updated_at); indexes (placement_id), (placement_id, event_type). Attribution stats only — no personal data (NFR-03).

### verification_cases + verification_documents
- **verification_cases**: id, subject_type ENUM(owner,property,listing), subject_id, owner_id FK, status ENUM(unverified,pending,verified,revoked), submitted_at, reviewed_by FK, decision_at, decision_note.
- **verification_documents**: id, case_id FK, document_id FK, type.

### reports + disputes + case_actions
- **reports**: id, reporter_id FK, subject_type ENUM(property,user), subject_id, category, description, status ENUM(open,under_review,resolved,dismissed,escalated), priority, resolved_by, resolved_at, resolution_note.
- **disputes**: id, dispute_type, source_type ENUM(deposit,invoice,lease), source_id, opener_id FK, opponent_id FK, status, decision, decided_by, decided_at.
- **case_actions**: id, caseable_type, caseable_id, actor_id FK, action, note, created_at.

### documents + document_versions + document_links
- **documents**: id, uploader_id FK, name, type, path, mime, size, visibility ENUM(public,private,admin), tags, retention_until, purged_at.
- **document_versions**: id, document_id FK, version_no, path, mime, size, uploaded_by, created_at.
- **document_links**: id, document_id FK, model_type, model_id, purpose (polymorphic).

### notifications (framework) + notification_preferences + notification_logs
- notification_preferences: id, user_id FK, event_type, channels JSON.
- notification_logs: id, notification_id FK, channel, status, delivered_at, error.

## 21.5 Key Relationship Rules

1. Owner ↔ Property: `properties.owner_id` FK to `auth_users` - one-to-many.
2. Property ↔ Agent-free: all interactions attach to `properties`, not to an agent table.
3. Apply exclusivity: `rental_applications` enforces one active application per `(property_id, applicant_id)` at the app layer.
4. Favourite uniqueness: DB unique constraint `(user_id, property_id)`.
5. Lease → Occupancy: property becomes `occupied` on lease activation; `reserved` on approval.
6. Route ↔ role: `role_routes` maps roles to routes; middleware enforces 403 at the group.

## 21.6 Seed Data

- Roles: Superuser, Admin, Owner, Tenant, Staff.
- Demo users (password `password123`): `admin@system.local` (Superuser), `owner@dzimba.local` (Owner), `tenant@dzimba.local` (Tenant), `staff@dzimba.local` (Admin).
- All demo users have `password_changed_at` set to avoid forced first-login change.