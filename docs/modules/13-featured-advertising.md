# Module 13 - Featured & Advertising Management

> Phase: Phase 2 | Primary actors: Owner (buyer), Admin (pricing & moderation), Tenant (sees placements)
> **Config-driven rule**: placement pricing, duration, max-per-property, auto-expiry, renewal and placement types read from `featured.*` configuration (Module 24) plus the `ad_packages` catalogue. No price/duration numbers in code.
> **Implementation status (Wave 4 slice 6 — DONE):** FR-01/FR-02 package catalogue (`ad_packages`: featured/top/homepage/premium_badge — price `DECIMAL(12,2)` + duration_days) and placement windows (`ad_placements`: state machine reserved→active→expired/cancelled, `paused` freezes the window) enforced exactly from config + catalogue — no code constants; purchase via an M9-style approval gateway (admin approve = settle `paid_at` + open window, plus `featured.approval_required` auto-activate switch). FR-03 `featured = true` on the property only while an active placement exists (`syncFeatured`, daily `ads:expire` sweep — NFR-01); marketplace featured-first sort + badges reuse the existing rail. FR-04 owner booking + history UI (`Owner/Advertising`, book-your-listing picker + per-placement impression/click/enquiry/application stats). FR-05 attribution stats (`ad_events`, no PII — NFR-03). FR-06 admin queue + moderation (`Admin/Advertising`: approve, pause/resume, cancel-with-prorated-credit in integer cents via `intdiv`; `featured.max_per_property` slot + `featured.max_active_per_owner` spend cap). FR-07 revenue reporting is Phase 2 (e.g. via Marketplaces analytics). Demo: 4 packages + two active placements + one reserved order seeded. Tracking in `docs/modules/23-implementation-plan.md` §1.5.

## 1. Purpose

Optional paid promotion that increases a listing's visibility. Generates an additional revenue stream beyond subscriptions and gives serious owners a competitive edge without agents.

## 2. Roles & Responsibilities

| Actor | Actions |
|---|---|
| **Owner** | Purchase featured slots/packages, pick target audience (city/type) and duration, track promo performance. |
| **Admin** | Configure packages and pricing (featured price/duration/placement types), approve premium placements, moderate ads, report ad revenue. |
| **Tenant** | Sees promoted listings and badges; can hide/see promoted placements. |

## 3. Functional Requirements

- FR-01 Packages: Featured Property, Top Placement, Homepage Promotion, Premium Property badge — priced/durationed from `featured.*` config and `ad_packages`.
- FR-02 Purchase applies a `featured = true` flag with start/end window; windows enforce exactly.
- FR-03 Featured listings sort above organic results (Module 03).
- FR-04 Admin can cancel placements with prorated refund (credit/refund per Module 24).
- FR-05 Performance stats: impressions, clicks, enquiries, applications.
- FR-06 Spend cap and budget controls per owner (config: `featured.max_per_property`, budgets).

## 4. Non-Functional Requirements

- NFR-01 Promotion windows are time-bounded and cron-cleaned (featured-expiry rule from Module 24 rules).
- NFR-02 No overlap: a property can hold only one active placement at a time.
- NFR-03 Click-through tracking without recording personal data unnecessarily.
- NFR-04 Changing placement price/duration config applies to new purchases; active windows keep their booked price (audited).

## 5. Workflows & Pseudo Sentences

1. **Purchase** - When an owner buys a Featured package, the system creates an ad order at the configured price; when payment settles, the system sets `featured = true` and records the window; then the system promotes the listing in search sort.
2. **Display** - When the marketplace queries listings, the system sorts active featured first; when the window ends, the cron sets `featured = false`; the listing returns to organic order.
3. **Cancel** - When admin cancels a placement, the system calculates the unused portion; then the system issues a prorated credit/refund; finally it disables the flag immediately.
4. **Report** - When an owner opens ad analytics, the system shows impressions, clicks and resulting enquiries/applications over the window.
5. **Moderation** - When a promoted property is reported as fake, the system pauses the placement; when admin resolves, the placement resumes or is refunded.

## 6. Data Model

| Table | Key Columns |
|---|---|
| `ad_placements` | id, property_id FK, owner_id FK, package_id FK, starts_at, ends_at, status, amount, created_at |
| `ad_packages` | id, name, placement_type, price, duration_days, description, status |
| `ad_events` | id, placement_id FK, event_type (impression/click/enquiry/application), created_at |
| `system_configurations` | Module 24 `featured.*`: price, duration, max_per_property, placement types, auto_expiry, renewal |

Relationships: `ad_placements` belongsTo property, owner, package; hasMany events.

## 7. Integrations & Dependencies

- Module 02 (`properties.featured`), Module 03 (sort), Module 09 (payment + approval), Module 24 (featured config + rules), Module 17 (ad revenue analytics), Module 19 (fake-promotion reports).

## 8. Acceptance Criteria

- AC-01 Featured listings respect their time windows exactly.
- AC-02 A property has at most one active placement.
- AC-03 Promotion performance is measurable per placement.
- AC-04 Placement price/duration/policy comes from configuration; code contains none of these numbers.