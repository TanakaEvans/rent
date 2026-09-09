# Module 13 - Featured & Advertising Management

> Phase: Phase 2 | Primary actors: Owner (buyer), Admin (pricing & moderation), Tenant (sees placements)

## 1. Purpose

Optional paid promotion that increases a listing's visibility. Generates an additional revenue stream beyond subscriptions and gives serious owners a competitive edge without agents.

## 2. Roles & Responsibilities

| Actor | Actions |
|---|---|
| **Owner** | Purchase featured slots/packages, pick target audience (city/type), set duration, track promo performance. |
| **Admin** | Define packages and pricing, approve premium placements, moderate ads, report ad revenue. |
| **Tenant** | Sees promoted listings and badges; can hide/see promoted placements. |

## 3. Functional Requirements

- FR-01 Packages: Featured Property, Top Placement, Homepage Promotion, Premium Property badge.
- FR-02 Purchase applies a `featured = true` flag with start/end window.
- FR-03 Featured listings sort above organic results (Module 03).
- FR-04 Admin can cancel placements with prorated refund.
- FR-05 Performance stats: impressions, clicks, enquiries, applications.
- FR-06 Spend cap and budget controls per owner.

## 4. Non-Functional Requirements

- NFR-01 Promotion windows are time-bounded and cron-cleaned.
- NFR-02 No overlap: a property can hold only one active placement at a time.
- NFR-03 Click-through tracking without recording personal data unnecessarily.

## 5. Workflows & Pseudo Sentences

1. **Purchase** - When an owner buys a Featured package, the system creates an ad order; when payment succeeds, the system sets `featured = true` and records the window; then the system promotes the listing in search sort.
2. **Display** - When the marketplace queries listings, the system sorts active featured first; when the window ends, the cron sets `featured = false`; the listing returns to organic order.
3. **Cancel** - When admin cancels a placement, the system calculates the unused portion; then the system issues a prorated credit/refund; finally it disables the flag immediately.
4. **Report** - When an owner opens ad analytics, the system shows impressions, clicks and resulting enquiries/applications over the window.
5. **Moderation** - When a promoted property is reported as fake, the system pauses the placement; when admin resolves, the placement resumes or is refunded.

## 6. Data Model

| Table | Key Columns |
|---|---|
| `ad_placements` | id, property_id FK, owner_id FK, package_id FK, starts_at, ends_at, status, amount, created_at |
| `ad_packages` | id, name, placement_type, price, duration, description |
| `ad_events` | id, placement_id FK, event_type (impression/click/enquiry/application), created_at |

Relationships: `ad_placements` belongsTo property, owner, package; hasMany events.

## 7. Integrations & Dependencies

- Module 02 (`properties.featured`), Module 03 (sort), Module 09 (payment), Module 17 (ad revenue analytics), Module 19 (fake-promotion reports).

## 8. Acceptance Criteria

AC-01 Featured listings respect their time windows exactly.
AC-02 A property has at most one active placement.
AC-03 Promotion performance is measurable per placement.