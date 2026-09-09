# Module 02 - Property Management

> Phase: MVP | Implemented (core) | Primary actors: Owner (driver), Admin (moderate), Tenant (view)

## 1. Purpose

The owner's core module for creating, publishing, managing and maintaining rental listings, plus the admin's ability to moderate and verify them and the tenant's ability to view them.

> **Market benchmark:** Listing taxonomy and card display follow the Zimbabwe/South Africa portals reviewed in Sept 2026 (classifieds.co.zw, propertybook.co.zw, property.co.zw, hararerentals.co.zw, seeff.co.zw). In particular the property-type list and the "N Bedroom Type in Suburb" title convention are taken from those sites.

## 2. Roles & Responsibilities

| Actor | Actions |
|---|---|
| **Admin** | Moderate listings, verify properties, mark verified badge, remove/edit non-compliant listings, review reported properties, configure categories/amenities. |
| **Owner** | Create/edit listings, upload photos, set price/deposit/lease terms, set availability, change status (available/reserved/occupied/unavailable), track enquiries/applications per property, manage multiple properties. |
| **Tenant** | View listing detail and gallery, see status and verified badge, save/applies to/enquire on property. |

## 3. Functional Requirements

- FR-01 Create a property with title, description, type, bedrooms/bathrooms, building size (m²) and land area (m² or acres for land plots), price, deposit, furnished flag, suburb, zone, city, address, amenities, cover image, available-from date.
- FR-02 Property types (Zim market taxonomy): house, flat, townhouse, cottage, room, garden-flat, student-accommodation, short-term, commercial, office, warehouse, shop-retail, industrial, land. MVP stores the current core set (house, flat, townhouse, cottage, room, commercial, land) — the extended list is Phase 2 / lookup-driven.
- FR-03 Statuses: `available`, `reserved`, `occupied`, `unavailable`.
- FR-04 Lifecycle: Draft → Pending Verification → Published → Reserved → Occupied → Unavailable.
- FR-05 Owner can manage unlimited property count based on their subscription plan.
- FR-06 Mark property featured / not featured (paid service).
- FR-07 Owner scoping: an owner only sees and edits their own properties.
- FR-08 Multi-image gallery, video, and documents attached to a listing.
- FR-09 Listing titles follow the market convention `{N} Bedroom {Type} {in} {Suburb}` (e.g. "3 Bedroom House in Borrowdale") — auto-built from the fields, so the owner does not hand-write titles.

## 4. Non-Functional Requirements

- NFR-01 Soft-delete/escalation: removed listings remain in DB for moderation audit.
- NFR-02 Image uploads validated for type/size; served with caching headers.
- NFR-03 Composite indexes support fast filtering by `(status, city)` and `(owner_id, status)`.
- NFR-04 Owners cannot be orphaned: deleting an owner cascades to their properties by design (MVP) - production should soft-delete instead.

## 5. Workflows & Pseudo Sentences

1. **Create draft** - When an owner submits the property form, the system validates required fields; then the system stores the record with status `available` (MVP) or `draft`; finally the system shows the property on the owner dashboard.
2. **Publish / verify** - When an owner publishes a listing, the system notifies admin for review; when admin approves and sets `verified = true`, the system displays the Verified Property badge on the marketplace.
3. **Mark reserved** - When a lease is generated from an approved application, the system sets status `reserved`; when both parties sign (lease `active`), the system sets status `occupied` automatically.
4. **Make unavailable** - When a property is removed from the market, the owner sets status `unavailable`; then the system hides it from tenant searches while keeping the record for reports.
5. **Feature** - When an owner purchases a featured slot, the system sets `featured = true` with a promotion window; when the window ends, the system sets `featured = false`.

## 6. Data Model

| Table | Key Columns |
|---|---|
| `properties` | id, owner_id (FK auth_users), title, description, property_type, bedrooms, bathrooms, building_size (m², nullable), land_size (m², nullable), price (12,2), deposit (12,2), furnished, status, suburb, zone, city, address, amenities (json), cover_image, featured, verified, available_from, timestamps |
| `property_images` (Wave 1) | id, property_id FK, path, caption, sort_order |
| `property_history` (Wave 1) | id, property_id FK, from_status, to_status, changed_by FK, timestamps |
| `property_*` (Phase 2) | `property_videos`, `property_documents` |

Relationships:

- `properties.owner_id` → `auth_users.id` (one-to-many: user owns many properties).
- `properties` hasMany `property_favourites` and `rental_applications` (see modules 04 and 07).
- `properties` hasMany `property_images` (gallery) and `property_history` (status audit).

### Status state machine (implemented)

Statuses: `available` / `reserved` / `occupied` / `unavailable`. Every allowed transition writes a `property_history` row (`changed_by` = acting user). Allowed transitions (`Property::TRANSITIONS`):

```text
available  → reserved, unavailable
reserved   → occupied, available
occupied   → available, unavailable
unavailable→ available
```

### Planned Phase-2 Tables

```text
property_videos:     id, property_id FK, path, title, sort_order
property_documents:  id, property_id FK, document_id FK (Module 20)
```

## 7. Integrations & Dependencies

- Module 03 (marketplace serves properties), 04 (favourites), 05 (enquiries), 06 (viewings), 07 (applications), 08 (leases), 10 (maintenance), 14 (verification), 16 (dashboard).

## 8. Acceptance Criteria

AC-01 An owner can publish and update listing, and only sees their own.
AC-02 A tenant searching sees published/available properties only.
AC-03 Verified badge persists for verified listings only.
AC-04 Feature flag controls promotion placement.