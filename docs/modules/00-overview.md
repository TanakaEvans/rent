# ZimRent - Module Documentation

> Property Rental Marketplace + Property Management ERP
> Generated: 2026-09-09 | App: Laravel + Inertia + React | DB: MySQL

This folder contains the detailed requirements and design documentation for the **ZimRent** platform, written per module and organized around the three parties that use the system:

| Party | Description |
|---|---|
| **Admin** | Platform operator / staff. Governs users, roles, verification, subscriptions, moderation, reporting and system configuration. |
| **Property Owner** | Landlord who lists and manages properties, reviews applicants, and runs their rental business. |
| **Tenant** | Seeker who browses, saves, enquires, views and applies for properties, then rents and manages the tenancy. |

## Reading Guide

| Document | Covers |
|---|---|
| [01 - User & Account Management](01-user-account-management.md) | Accounts, roles, sessions, security. |
| [02 - Property Management](02-property-management.md) | The owner's core listing and management module. |
| [03 - Marketplace & Search](03-marketplace-search.md) | Public catalogue, filters, property discovery. |
| [04 - Favourites & Saved Searches](04-favourites-saved-searches.md) | Tenant shortlisting and search alerts. |
| [05 - Enquiries & Communication](05-enquiries-communication.md) | Tenant-to-owner messaging and enquiries. |
| [06 - Property Viewings](06-property-viewings.md) | Requesting, scheduling and confirming viewings. |
| [07 - Rental Applications](07-rental-applications.md) | Applying for properties and owner review. |
| [08 - Lease & Agreements](08-lease-agreements.md) | Lease creation, renewal and termination. |
| [09 - Rent & Payments](09-rent-payments.md) | Invoicing, rent collection, receipts. |
| [10 - Maintenance](10-maintenance.md) | Tenant-reported and owner-maintained repairs. |
| [11 - Contractors](11-contractors.md) | Tradespeople assigned to maintenance jobs. |
| [12 - Subscriptions](12-subscriptions.md) | Owner plans and platform billing. |
| [13 - Featured & Advertising](13-featured-advertising.md) | Paid visibility/promotion of listings. |
| [14 - Verification](14-verification.md) | Owner / property / listing trust badges. |
| [15 - Notifications](15-notifications.md) | In-app, email, SMS and WhatsApp channels. |
| [16 - Landlord Dashboard](16-landlord-dashboard.md) | Owner operational overview. |
| [17 - Reports & Analytics](17-reports-analytics.md) | Owner and platform analytics. |
| [18 - Admin/System Management](18-admin-system-management.md) | Platform administration console. |
| [19 - Complaints & Disputes](19-complaints-disputes.md) | Reporting, moderation and resolutions. |
| [20 - Document Management](20-document-management.md) | Central document repository. |
| [21 - Database Design](21-database-design.md) | Full data model, columns and relationships. |
| [22 - System Workflow](22-system-workflow.md) | End-to-end lifecycle and pseudo workflows. |
| [23 - Implementation Plan](23-implementation-plan.md) | Phased build plan, priorities and sequencing. |
| [24 - System Configuration](24-system-configuration.md) | **Configuration-driven engine**: every commercial/billing rule is data, changed by staff, never code. |

## Module Map

ZimRent is laid out in four areas:

```
                  ZIMRENT PLATFORM
                        │
        ┌───────────────┼─────────────────┐
        │               │                 │
     TENANT          OWNER             ADMIN
        │               │                 │
        ▼               ▼                 ▼
   Marketplace     Owner ERP        Administration
        │               │                 │
        └───────────────┼─────────────────┘
                        │
                 CORE SERVICES
                   Payments • Notifications • Documents • Reports
```

## 3-Party Module Matrix

| Module | Admin | Owner | Tenant |
|---|---|---|---|
| 01 User & Account | Manage all accounts | Register, manage profile | Register, manage profile |
| 02 Property Management | Moderate/verify | **Primary user** | View only |
| 03 Marketplace & Search | Configure | List | **Primary user** |
| 04 Favourites & Saved Searches | - | - | **Primary user** |
| 05 Enquiries & Communication | Monitor/audit | Respond | Initiate |
| 06 Property Viewings | Monitor slots | Confirm/schedule | Request |
| 07 Rental Applications | Oversee | Review/accept | Submit |
| 08 Lease & Agreements | Templates | Create/sign | Sign |
| 09 Rent & Payments | Platform payouts | Collect | Pay |
| 10 Maintenance | Escalation | Assign/approve/close | Report + confirm |
| 11 Contractors | Registry | Hire / assign / rate | Receive job brief |
| 12 Subscriptions | **Primary user** | Subscribe/upgrade | - |
| 13 Featured & Advertising | Pricing/approve | Buy | See badges |
| 14 Verification | **Primary user** | Submit evidence | See badges |
| 15 Notifications | Global config | Receive | Receive |
| 16 Landlord Dashboard | - | **Primary user** | - |
| 17 Reports & Analytics | Platform-wide | Own-data | Own-data (light) |
| 18 Admin/System Management | **Primary user** | - | - |
| 19 Complaints & Disputes | Resolve | Respond | Report |
| 20 Document Management | Registry | Upload | Upload |
| 24 System Configuration | **Primary user** (Config Centre) | - | - |
| 25 Presentation Release (S1–S11) | Moderate/badges | List (step form, USD/ZWL) | Discover/filter/apply |

## Status Legend

| Tag | Meaning |
|---|---|
| `MVP` | Required for the first working release. |
| `Phase 2` | Added after MVP validation. |
| `Phase 3` | Expansion / commercial / commercial-property phase. |
| `Implemented` | Backend and/or UI already exists in the codebase. |
| `Partially implemented` | Some pieces exist (e.g. tables or pages) but flows are incomplete. |

## Current Build Snapshot

Already present in the codebase:

- `auth_users`, `auth_roles`, `auth_user_roles`, `auth_login_logs` - authentication and roles.
- `companies`, `branches`, `departments`, `employees`, `sections`, `system_settings` - system administration.
- `properties`, `property_favourites`, `rental_applications`, `property_history`, `property_views`, `saved_searches`, `reports` - marketplace tables.
- Roles: `Superuser`, `Admin`, `Owner`, `Tenant`, `Staff`, `Contractor` (Wave 5 slice 2 — job-desk users, not platform subscribers).
- Maintenance + Contractors (Wave 5): `maintenance_requests`/`maintenance_actions` + `contractors`/`contractor_trades`/`contractor_ratings`, SLA sweep, escalation queue, contractor registry, verified-only assignment with approved quote, full life-cycle (assign → start → complete → tenant confirm → owner close, `resolved_at` → owner rates the contractor).
- **Presentation Release 2.0 S1 (DONE)**: `property_type` → string catalogue (apartment now its own type) + rates/layout columns (`currency` USD/ZWL, `payment_terms`, monthly water/electricity/refuse, `negotiable`, security/parking/entrance/bathroom types, family/pet/smoking/party rules, `minimum_stay`, `preferred_tenant`, `landlord_type`, `contact_preference`/`show_phone`, `landmark`, `floor_area`, `year_built`, `distance_to_cbd`); step listing form (Basics / Pricing / Rental details / Photos) with cover + gallery uploader (`storage/app/public/properties/{id}/`, min-1-photo on create); shared `lib/listing.js` catalogue drives every money/type rendering marketplace + owner + tenant + admin; seeded "2 Bedroom Apartment in Avondale" demo listing. Full suite 403 passed / 1945 assertions, build clean.
- **Presentation Release 2.0 S2 (DONE)**: marketplace gains 4 filters over the S1 taxonomy (`payment_terms`, `security_type`, `parking_type`, `preferred_tenant` — exact-set, `Rule::in`-validated), a **Top-rated** sort (owner `rating_avg`/`ratings_count` via scalar subqueries, `featured` pinned first), and **verified-owner badge groundwork** — `auth_users` + `badge_tier` (gold/silver/bronze/none, `2026_09_09_000033`), new shared `OwnerTrustBadge` on marketplace cards/detail (fixes the S1 bug where card owner selects dropped `verified`); natural-language search isolates `apartment` from `flat`; demo owner is verified gold 4.6★. Full suite 412 passed / 2109 assertions, build clean. (See `docs/modules/25-presentation-release.md`.)
- **Presentation Release 2.0 S3 (DONE)**: public self-service signup — `AuthService::registerTenant` creates an active user (email lowercased, `password_changed_at` stamped, `username` auto-derived from the email local-part with suffix-on-collision) and assigns the **`Tenant` role by default** (`assigned_by` null — no unassigned signups; staff/moderators stay invitation-only); new `Auth\RegisterController` (guest-only, auto-login, regen session, success flash → tenant dashboard) + `register`/`register.submit` routes + `Pages/Auth/Register.jsx` + Login create-account link; no migration. Tests: `SignupTest` (11) + access matrix +3. Full suite 426 passed / 2163 assertions, build clean.
- **Presentation Release 2.0 S4 (DONE)**: tenant profiles + KYC — `tenant_profiles` (contact/employment/income-band/preferred-contact/about) + `identity_documents` (national/physical ID card + driving licence, **one scan per type, re-upload replaces + status resets pending**, `unique(user_id,type)`) + `identity_document_audits` (`uploaded`/`replaced`/`removed`, trail survives deletion via nullOnDelete) on **private disk** `storage/app/private/kyc/{id}/` (never public/); `TenantProfileService` row-scoped (owner or Admin/Superuser else 404); `/tenant/profile` page (profile form + per-type KYC cards with upload/replace/download/remove) under the tenant nav; approve/revoke flow lands in **S5**; demo tenant profile seeded (identity scans not seeded — uploads demoed in-browser). Tests: `TenantProfileTest` (12) + access matrix +3. Full suite 441 passed / 2259 assertions, build clean.
- **Presentation Release 2.0 S5 (DONE)**: identity review + derived badges — `identity_document_audits.action` widened (`2026_09_09_000035`); `IdentityDocument` state machine (`pending → approved|rejected`, `approved → rejected`, rejected terminal); `TenantProfileService` derived ladder (`KYC_TIERS` none/basic/full from approved types; `badgeTierFor` full→gold, basic→silver, any pending/approved→bronze, else none; `recomputeBadgeTier` persisted on `auth_users`, never a manual toggle) + `approve`/`reject(note)`/`revoke(note)` guarded by `reviewable()` (Admin/Superuser 403, missing 404, illegal 409) with audit rows + `KycDocumentStatusNotification`; `AdminKycController` + 5 `admin.kyc.*` routes + `Admin/Kyc/Index` page (filter chips, tier pills, approve/reject/revoke/view) + AdminLayout nav; tenant profile shows the derived badge/KYC tier; demo owner seeds two approved metadata-only rows → gold, `PropertySeeder` no longer hardcodes `badge_tier`. Tests: `KycAdminTest` (9) + access matrix +3. Full suite 452 passed / 2358 assertions, build clean.
- Routes/UI: public marketplace `/` (search + filters + map + saved searches + lifecycle + trust/reports + owner/admin analytics), login, `/dashboard`, `/admin/dashboard`, `/owner`, `/tenant`.