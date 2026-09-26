# Module 25 — Presentation Release 2.0

Owner-approved build batch (Sept 2026). Everything here lands as **permanent production org
features** — no demo hacks, full role matrix, full tests. Tracking lives in
`docs/modules/23-implementation-plan.md` §1.5 (the mandatory status tracker). Each slice is a
vertical end-to-end slice: migration → model → service/controller → route → Inertia page →
shared component → tests → docs, in one sitting.

## Approved batch (S1–S11)

| Slice | Feature | Status |
|---|---|---|
| S1 | Zimbabwe categories + step listing form + photo upload + dual currency (USD/ZWL) | **DONE** |
| S2 | Marketplace filters + verified badges + Top-rated sort | **DONE** |
| S3 | Public self-service signup (default Tenant role) | **DONE** |
| S4 | Tenant profiles + KYC (physical / SAPS-like ID / driving licence, private disk + audit) | **DONE** |
| S5 | KYC approve/revoke by Admin/Superuser; badge tier off KYC tier | **DONE** |
| S6 | Express-Interest queue (mass-enquiry building block) | pending |
| S7 | Viewing calendar | pending |
| S8 | Viewing ratings | pending |
| S9 | Net-tenancy scores | pending |
| S10 | Social login (Google + Facebook via Socialite, feature-flagged) | pending |
| S11 | Landlord analytics expansion + Rocket documentation | pending |

Notes on the batch (user-approved decisions):

- **Public signup (S3)** replaces the invitation-only flow for tenants; new users get the
  `Tenant` role by default and are never stuck unassigned. Staff/moderators still invitation-only.
- **Notifications:** email + in-app now — SMS/WhatsApp slots stay config-wired adapters (SMS/WhatsApp
  providers land later; nothing is hard-wired to a single provider).
- **Social login (S10):** implementation now, feature-flagged off until live OAuth credentials
  (Google + Facebook) are supplied; never unpinned by default.
- `apartment` is its own property type (Phase-1 residential taxonomy — see `02-property-management.md`).
- Bronze/silver/gold badges and verified badges (S2/S5) derive from KYC tier + verification decisions —
  never manual UI fluff.

---

## S1 — Rates & presentation (DONE, 2026-09-18)

Conversion of the marketplace to the full Zimbabwe-residential taxonomy with rich per-listing
rates and layout, a step-based listing entry form, real photo uploads, and dual currency.

### Backend

- **Migration** `2026_09_09_000032_add_marketplace_layout_and_pricing_to_properties_table.php`:
  - `property_type` ENUM → `string(50)` (catalogue grows without migrations).
  - New columns (all nullable-or-defaulted, forward compatible):
    `floor_area` DECIMAL(10,2), `year_built` SMALLINT/unsigned, `currency` USD|ZWL default USD,
    `payment_terms` monthly|quarterly|yearly default monthly, `water_cost`/`electricity_cost`/
    `trash_cost` DECIMAL(10,2) default 0, `negotiable` bool default false,
    `entrance_type`/`bathroom_type`/`parking_type`/`security_type` strings nullable,
    `families_allowed`/`children_allowed`/`pets_allowed`/`smoking_allowed`/`parties_allowed`
    bool default false, `distance_to_cbd` DECIMAL(10,2) nullable, `minimum_stay` unsignedInt
    default 6, `preferred_tenant` string default `any`, `landlord_type` string default `direct`,
    `contact_preference` string default `platform`, `show_phone` bool default false,
    `landmark` string nullable.
- **`app/Models/Property.php`**: expanded fillable; casts (money decimals float, bools, array);
  catalogues `TYPES` (room, flat, apartment, house=`Full house`, townhouse, cottage, commercial,
  land), `CURRENCIES`, `PAYMENT_TERMS`, `PARKING_TYPES`, `SECURITY_TYPES`, `PREFERRED_TENANTS`,
  `LANDLORD_TYPES`, `CONTACT_PREFERENCES`.
- **`app/Services/PropertyService.php`**: `create()`/`update()` accept `array $media`;
  `syncMedia()` is the **grid-as-source-of-truth** writer:
  - stores uploaded cover/gallery on the public disk under `properties/{id}/`,
  - rebuilds `property_images` rows with `sort_order`,
  - **no-op when no new files are supplied** (retained-path edits never churn gallery/cover),
  - only ever deletes `/storage/properties/` files — seeded `/uploads/...` artwork is never removed.
- **`app/Http/Controllers/PropertyController.php`**: `rules()` covers the new fields
  (`Rule::in` against the model catalogues), `images.*` accepts an UploadedFile image <8MB **or**
  a retained string path ≤255, `images` max 8, `cover` image; **min-1-photo gate on create only**
  (error key `media`); `amenityOptions()` serves `[{key,label}]` from `config('marketplace.amenities')`
  to create/edit; `update` passes media too.
- **Seeder** (`database/seeders/PropertySeeder.php`): "2 Bedroom Apartment in Avondale" —
  USD 700, zone **Harare East** (zone-filter determinism), gated security,
  `backup_power`/`secure_parking`/`water_tank`, bedrooms 2 / bathrooms 1 (at-least-filter tests stay
  stable), verified, custom lat/lng, `available_from` null.

### Frontend

- **`resources/js/lib/listing.js`** — single ISO-ZW catalogue shared by every page:
  `TYPE_LABELS` (house → “Full house”, flat → “Flat”, apartment → “Apartment”), currency symbols,
  `typeLabel()`, `currencySymbol()`, `formatPrice(value, currency = 'USD')` (`$` / `ZWL `),
  `priceSuffix(payment_terms)` (`/month` | `/quarter` | `/year`), `paymentPeriod(terms)`,
  option-label maps, `yesNoOrDash`, `optionLabel`.
- **`resources/js/Components/Shared/PhotoUploader.jsx`** — cover + up-to-8 gallery uploader,
  blob previews (revoked on unmount), remove, error display, `onChange({cover, images})`.
- **`resources/js/Pages/Owner/Properties/PropertyForm.jsx`** — 4-step wizard on create (Basics /
  Pricing / Rental details / Photos), stacked sections + photo manager on edit, amenity chips,
  `useForm` with File objects (multipart via Inertia).
- **`Create.jsx` / `Edit.jsx`** rewritten as thin pages around the form.
- **Marketplace** `Index.jsx` & `Show.jsx` — currency-aware prices, payment-terms suffix,
  apartment icon/tone, “Good to know” facts panel, Cost breakdown (rent + deposit + monthly
  water/electricity/refuse + term-aware move-in + negotiable badge).
- **Owner** `Properties/Show.jsx` (currency price + expanded Listing Facts), `Properties/Index.jsx`,
  `Dashboard.jsx`, `Analytics.jsx`; **Tenant** `Dashboard.jsx`, `Favourites.jsx`,
  `SavedSearches.jsx`; **Admin** `Marketplace/Analytics.jsx` — all unified on `@/lib/listing`
  (per-listing prices carry the listing’s currency; aggregates stay USD default).

### Tests & verification

- `ListingAndDiscoverTest`: `validPayload` gained the required S1 fields + `cover` UploadedFile;
  `Storage::fake('public')` in `setUp`; counts recalibrated (price-range 2→3, filter-combo 3,
  furnished 3, ignores-invalid 6, paginate total 19 / page-2 7).
- `MonetiseTest::propertyPayload()` and `ConfigurationTest` over-limit POST gained the new required
  fields + fake cover so the quota/entitlement gates are reached.
- **Environment fix discovered during verification:** stale `bootstrap/cache/config.php` made the
  test suite boot against MySQL + `file` sessions → every POST returned CSRF **419** and tests took
  8–10 s. Fixed with `php artisan optimize:clear` (suite back on sqlite `:memory:`). Guard: re-run
  `php artisan test` after any `config:cache`/`optimize` and keep the bootstrap cache clear on the
  development machine.
- Full suite **403 passed / 1945 assertions**; `npm run build` clean; dev DB `migrate:fresh --seed`
  OK; no orphan/duplicate routes.

### Demo data

`owner@dzimba.local` / `tenant@dzimba.local` / `admin@system.local` (password `password123`) can
walk: owner creates a new listing through the 4-step wizard with photos and USD/ZWL pricing; tenant
sees the Avondale apartment with currency-aware price, payment-terms suffix and the structured
“Good to know”/“Cost breakdown” panels on the marketplace and detail pages.

---

## S2 — Marketplace filters, verified badges, Top-rated (DONE, 2026-09-18)

Extended marketplace filters, verified-owner badge groundwork (KYC-tier typed), and a Top-rated
sort rail.

### Backend

- **Migration** `2026_09_09_000033_add_badge_tier_and_owner_ratings_to_auth_users_table.php`:
  `auth_users` gains `badge_tier` string(20) default `none`, `rating_avg` DECIMAL(3,2) default 0,
  `ratings_count` unsignedInteger default 0.
- **`app/Models/User.php`**: `BADGE_TIERS` (`gold`/`silver`/`bronze`/`none`) + fillable/casts.
- **`app/Services/PropertySearchService.php`**:
  - New filters over the S1 taxonomy: `payment_terms`, `security_type`, `parking_type`,
    `preferred_tenant` (exact-set semantics, validated in `HomeController::normalizeFilters`
    against the model catalogues).
  - New `top_rated` sort branch — **orders by owner rating through scalar subqueries, not a
    LEFT JOIN** (`CASE WHEN ratings_count>0 THEN 0 ELSE 1 END` ASC, then `rating_avg` DESC, then
    recency; `featured` stays pinned first). The join-based first attempt broke `paginate()`'s
    count query (ambiguous `status` after the join); the subquery form keeps the count clean on
    both MySQL and SQLite.
  - Owner eager-loads now ship `owner:id,name,email,verified,badge_tier` on search, featured,
    just-listed and public-detail queries — **this fixes the S1-era hidden bug where the card
    owner select excluded `verified`, so the Verified-owner pill could never render.**
- **`app/Services/NaturalLanguageSearchService.php`**: `apartment`/`appartment` map to
  `apartment` (its own type) instead of being collapsed into `flat`.
- **Seeder** (`PropertySeeder`): demo owner becomes verified (gold tier, 4.6★ / 14 ratings).
  No new listings — sandbox exact-count tests stay stable.

### Frontend

- **`resources/js/lib/listing.js`**: `BADGE_TIERS` + `badgeTierLabel`.
- **`resources/js/Components/Shared/OwnerTrustBadge.jsx`** (new): renders the gold/silver/bronze
  tier pill when `badge_tier != 'none'`, else the plain Verified-owner pill when `owner.verified`,
  else nothing. Replaces the previously-dead verified pill on marketplace cards.
- **`Marketplace/Index.jsx`**: `payment_terms` select in the main filter grid; security/parking/
  preferred-tenant selects in the More-filters panel; `top_rated` sort option; badge pills on
  cards, hero and quick-view.
- **`Marketplace/Show.jsx`**: rail card + owner info block use `OwnerTrustBadge`.

### Tests & verification

- `ListingAndDiscoverTest` +8 wave cases: exact-set filters for all four new keys, apartment↔flat
  isolation, top_rated orders rated owners first then by rating then recency, featured-first
  stability under top_rated, owner `verified`+`badge_tier` exposed in props, natural-language
  apartment parse; the invalid-values case was extended with all four new keys.
- Full suite **412 passed / 2109 assertions**; `npm run build` clean; dev DB
  `migrate:fresh --seed` OK; no orphan/duplicate routes.

### Demo data

`owner@dzimba.local` (gold verified owner) / `tenant@dzimba.local` / `admin@system.local`
(password `password123`) can walk: tenant filters the marketplace by payment terms, security,
parking and preferred tenant; toggles the Top-rated sort and sees the verified gold-owner listings
ranked first; owner/tenant see the gold tier badge on cards and on the property detail owner block.

---

## S3 — Public self-service signup (DONE, 2026-09-18)

Replaces the invitation-only tenant flow with public self-service registration. New users land
with the `Tenant` role by default and are never stuck unassigned; staff/moderators stay
invitation-only. **No schema change** — the `auth_users` table already supports it.

### Backend

- **`app/Services/AuthService.php`** (new): `registerTenant()` creates an active user with email
  lowercased, `password_changed_at` stamped at signup (no forced-change loop), and a `username`
  **auto-derived from the email local-part** — sanitised to `[a-z0-9._-]`, capped at 60 chars,
  with a numeric suffix on collision (`jane@…` → `jane`, next `jane1`) since `auth_users.username`
  is NOT NULL + unique. The `Tenant` role is assigned by default (pivot `assigned_by` null).
- **`app/Http/Controllers/Auth/RegisterController.php`** (new): guest-only `create`/`store`.
  Authenticated visitors are bounced to their own dashboard via `LoginController::landingFor`
  (that helper was promoted from `protected` to `public`). Validation: `name` required max:150,
  `email` required email max:150 `unique:auth_users`, `password` required `confirmed` +
  `Rules\Password::defaults()` (min 8 — there is no password-rules config in `config/auth.php`),
  `terms` required `accepted`. Success auto-logs in, `session()->regenerate()`, success flash, and
  redirects to `tenant.dashboard`.
- **Routes**: `register` (GET) + `register.submit` (POST) in `routes/web.php`.

### Frontend

- **`resources/js/Pages/Auth/Register.jsx`** (new): mirrors the Login split-page (dark brand panel
  + form) with name, email, password (show/hide), password confirmation, a terms-required
  checkbox, and links back to sign-in and the home page.
- **`resources/js/Pages/Auth/Login.jsx`**: plus "New to ZimRent? Create a free tenant account" link.

### Tests & verification

- New `tests/Feature/SignupTest.php` (11 cases): guest sees the register page; signup creates an
  active user with the `Tenant` role, `assigned_by` null and `locked_at` null; auto-login after
  signup; duplicate email rejected; weak password rejected; confirmation mismatch rejected;
  missing terms rejected; username derived from email; username collision gets the numeric suffix;
  a signed-up tenant can log in again; authenticated users are redirected from the register page.
- `DzimbaAccessControlTest` +3: guest may view the register page, authenticated users are
  redirected, and an Owner cannot POST the public signup (no intruder user is created).
- Full suite **426 passed / 2163 assertions**; `npm run build` clean; route:list shows only the
  intended new `register`/`register.submit` routes — no orphans or duplicates.

### Demo data

Signup needs no seed changes (demo users already exist). Walk-through: a guest opens
`/register`, signs up with name/email/password + terms, and lands on the tenant dashboard already
signed in; `/login` now offers the create-account path for new tenants.

---

## S4 — Tenant profiles & KYC identity (DONE, 2026-09-18)

Tenant surfaces a profile for owners and uploads identity evidence (national/physical ID card and
driving licence scans), stored on the **private disk** with a full audit trail. Approve/revoke by
Admin/Superuser lands in **S5** — uploads here land in a `pending` state only.

### Backend

- **Migration** `2026_09_09_000034_create_tenant_profiles_and_identity_tables.php`:
  - `tenant_profiles`: `user_id` unique FK→`auth_users` (cascade), `phone` ≤30, `city` ≤100,
    `employment_status`, `salary_band`, `preferred_contact`, `about` ≤1000 — all nullable.
  - `identity_documents`: `user_id` FK (cascade), `type` (`national_id` | `driving_licence`),
    `file_path`, `original_name` ≤255, `mime`, `size`, `status` (`pending`|`approved`|`rejected`,
    default `pending` — the approve/reject flow is S5), **`unique(user_id, type)`** — one scan per
    type; re-upload **replaces** the file and resets the status to `pending`.
  - `identity_document_audits`: `user_id` FK (cascade), `document_id` nullable FK→
    `identity_documents` **nullOnDelete** (the trail survives deletion), `action`
    (`uploaded`|`replaced`|`removed`), `actor_id` nullable→`auth_users`, `details`.
- **`app/Models/TenantProfile.php` / `IdentityDocument.php` / `IdentityDocumentAudit.php`** (new):
  catalogue constants (`EMPLOYMENT_STATUSES`, `SALARY_BANDS`, `PREFERRED_CONTACTS`, `KYC_TYPES`)
  + relations + casts.
- **`app/Models/User.php`**: `tenantProfile()`, `identityDocuments()`, `identityDocumentAudits()`.
- **`app/Services/TenantProfileService.php`** (new, `PRIVATE_DISK = 'local'`):
  - `profileFor` / `updateProfile` (`updateOrCreate`).
  - `uploadDocument` — `storeAs` on the **private `local` disk** under `kyc/{user_id}/…` (never
    under `public/`); the DB row keeps metadata only; replace-or-create + `uploaded`/`replaced` audit.
  - `authorize` — owner **or** Admin/Superuser, everyone else **404** (row existence never leaks).
  - `removeDocument` — authorize first, audit `removed`, then file + row delete.
- **`app/Http/Controllers/TenantProfileController.php`** (new): `show`/`update`/
  `storeDocument`/`destroyDocument`/`downloadDocument`; validation: `type` `Rule::in(KYC_TYPES)`,
  `document` `mimes:jpg,jpeg,png,pdf max:4096`, catalogue fields nullable `Rule::in`; options ship
  `[{key,label}]` per catalogue + `kyc_types`.
- **Routes** (5, in the `role:Tenant` group): `tenant.profile` GET/PUT,
  `tenant.profile.documents.store` POST, `destroy` DELETE + `download` GET (`whereNumber`).

### Frontend

- **`resources/js/Pages/Tenant/Profile.jsx`** (new): profile `useForm` (phone, city, employment,
  income band, preferred contact, about) + per-type KYC cards — upload / replace-with-confirm,
  `StatusBadge` (`pending` amber), Download + Remove (with confirm) that keep the audit-trail note.
- **`resources/js/Layouts/MainLayout.jsx`**: "My Profile" nav entry (`IdCard`) for the Tenant group.

### Tests & verification

- New `tests/Feature/TenantProfileTest.php` (12 cases): page + options props; update; clear fields;
  profile created when absent; catalogue validation; private-disk upload (path, `pending`, `uploaded`
  audit); same-type replace (`replaced` audit, old file removed); type/mime guard; own download
  (`content-disposition` + `content-type`); cross-tenant 404 (download + delete); remove keeps the
  audit trail and deletes the file; owner 403 incl. delete/download of a real document.
- `DzimbaAccessControlTest` +3: tenant 200 (component asserted), owner 403 across all five routes,
  guest redirects across all five routes.
- Full suite **441 passed / 2259 assertions**; `npm run build` clean; dev DB
  `migrate:fresh --seed` OK; route:list clean — no orphan/duplicate names.

### Demo data

`tenant@dzimba.local` (password `password123`) walks `/tenant/profile`: seeded profile details
(phone, Harare, employed, $501–$1,000, in-app contact); uploads a national-ID scan → lands on the
private disk as `pending` with the `uploaded` audit row; replaces it (`replaced` audit); removes it
(`removed` audit, file gone). Identity scans are **not** seeded — the seeder writes the profile
only, so seeders stay file-free and KYC uploads are demonstrated in-browser.

---

## S5 — Identity review & derived badge tiers (DONE, 2026-09-18)

Admin/Superuser approve, reject and revoke tenant identity evidence; the bronze/silver/gold badge
and the none/basic/full KYC tier derive from those decisions — never a manual toggle.

### Backend

- **Migration** `2026_09_09_000035_widen_identity_document_audit_actions.php`: the
  `identity_document_audits.action` ENUM is widened to `string(20)` so review decisions
  (`approved`/`rejected`/`revoked`) join the self-service actions.
- **`app/Models/IdentityDocument.php`**: `STATUSES` + explicit `TRANSITIONS`
  (`pending → approved|rejected`, `approved → rejected`, `rejected` terminal) + `canTransitionTo()`.
- **`app/Models/IdentityDocumentAudit.php`**: `ACTIONS` = `uploaded`/`replaced`/`removed`/
  `approved`/`rejected`/`revoked`.
- **`app/Services/TenantProfileService.php`** (extends S4):
  - Derived ladder — `KYC_TIERS` (none/basic/full from the set of **approved** doc types);
    `badgeTierFor()` maps full→gold, basic→silver, any pending/approved evidence→bronze, else none;
    `recomputeBadgeTier()` persists the derived badge onto `auth_users` via a direct table write
    (no `updated_at` churn). Uploads/removals and every review decision recompute.
  - Review flows — `approve`, `reject (note ≤500)`, `revoke (note)`; private `reviewable()` guards
    Admin/Superuser (403), missing rows (404) and illegal transitions (**409** `HttpException`);
    each decision writes an append-only audit row and sends `KycDocumentStatusNotification`
    (in-app title/body/note, deep-link `tenant.profile`).
- **`app/Notifications/KycDocumentStatusNotification.php`** (new): `via=['database']`, payload
  `match`es `approved`/`rejected`/`revoked`, body carries the reviewer's note when given.
- **`app/Http/Controllers/AdminKycController.php`** (new): `index` (status filter + paginated queue
  rows + pending/approved/rejected counts + label options), `approve`/`reject`/`revoke` POSTs,
  `download` streams the private-disk scan to reviewers (404 guard for seeded rows with no file).
- **Routes** (5, in the `admin` group): `admin.kyc.{index,approve,reject,revoke,download}`
  (`whereNumber` on `document`, descriptions on each).
- **Seeders**: `AuthSeeder` gives the demo owner two **approved metadata-only** identity rows
  (no files) and recomputes its badge → gold; `PropertySeeder` no longer hardcodes `badge_tier`
  (derivation owns it).

### Frontend

- **`resources/js/Pages/Admin/Kyc/Index.jsx`** (new): stats cards, status filter chips, queue table
  (tenant, document with mime/size, derived KYC-tier pill, submitted date, StatusBadge), per-status
  actions — Approve / Reject (prompt for note) / Revoke (prompt for reason) / View (new-tab
  download) — plus pagination.
- **`resources/js/Layouts/AdminLayout.jsx`**: "KYC Review" nav entry (`IdCard`).
- **`resources/js/Pages/Tenant/Profile.jsx`**: badge banner (badge tier + derived KYC tier) above
  the KYC cards; controller ships `kyc_tier` + `badge_tier`.

### Tests & verification

- New `tests/Feature/KycAdminTest.php` (9 cases): queue tiers/counts/filters (incl. the seeded gold
  pair); approve→silver→gold with audit + notification; reject-with-note trail + notification body;
  revoke drops the badge; illegal-transition 409s; admin downloads a real scan; seeded metadata row
  download 404s; tenant 403 across the review flows.
- `TenantProfileTest`: the upload-reject count assertion is now scoped to the tenant (the owner's two
  seeded rows exist).
- `DzimbaAccessControlTest` +3: admin + staff reach the queue; tenant/owner 403 on index/approve/
  download; guest redirects.
- Full suite **452 passed / 2358 assertions**; `npm run build` clean; `route:list` clean; dev DB
  `migrate:fresh --seed` OK (owner badge recomputes to gold, tenant stays none).

### Demo data

Start as anyone: sign in as `admin@system.local` (Superuser) or `staff@dzimba.local` (Admin) and
open Admin → KYC Review: the demo owner's gold pair shows under Approved with derived tiers; ask the
demo tenant to upload a scan, then approve it (badge jumps bronze→silver, tenant receives an in-app
notification), reject one with a note, and revoke an approved document to watch the ladder drop.

---

## S6–S11 (pending)

S6 Express-Interest queue; S7 viewing calendar;
S8 viewing ratings; S9 net-tenancy scores; S10 social login; S11 landlord analytics + Rocket docs.
See the batch table at the top and the §1.5 tracker for the live NEXT UP.