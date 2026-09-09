# Module 04 - Favourites & Saved Searches

> Phase: MVP core (favourites implemented) | Phase 2 (saved searches) | Primary actors: Tenant

## 1. Purpose

Lets tenants shortlist properties they like and (Phase 2) persist search criteria so they get alerted when new matching properties are listed - turning discovery into an ongoing relationship with the platform.

## 2. Roles & Responsibilities

| Actor | Actions |
|---|---|
| **Tenant** | Save/remove favourites, view saved list, compare shortlisted items, save searches, manage alert preferences. |
| **Owner** | Indirectly benefits when their property is saved/followed. |
| **Admin** | Configures search-alert throttling and email/SMS templates (Phase 2). |

## 3. Functional Requirements

- FR-01 Toggle a property as favourite from the marketplace card or detail page.
- FR-02 List favourites on the tenant dashboard with thumbnail, price and status.
- FR-03 A tenant can remove favourites in one click.
- FR-04 Phase 2: save a search (filters + criteria) under a name.
- FR-05 Phase 2: run saved searches on a schedule and notify when new matching properties appear.
- FR-06 Phase 2: compare up to N shortlisted properties side-by-side.

## 4. Non-Functional Requirements

- NFR-01 Favourite toggle is idempotent (same pair never duplicates).
- NFR-02 Unique constraint `(user_id, property_id)` on `property_favourites`.
- NFR-03 Cascade delete when a property is deleted.

## 5. Workflows & Pseudo Sentences

1. **Save** - When a tenant clicks the heart on a listing card, the system POSTs `property_id`; when a matching row exists, the system removes it (toggle off); when no row exists, the system inserts `(user_id, property_id)`; the system re-renders the icon state.
2. **View list** - When a tenant opens My Favourites, the system joins favourite rows to their properties; the system shows availability status stickers; when a property becomes unavailable, the system still shows it but greyed out.
3. **Remove** - When a tenant removes a favourite, the system deletes the pivot row; the system decrements the favourite count on the dashboard.
4. **Save search (Phase 2)** - When a tenant saves the current filter state, the system stores the criteria JSON; then the scheduler runs saved searches nightly; when a new property matches, the system creates a notification and optional email.
5. **Compare (Phase 2)** - When a tenant selects compare, the system builds a comparison grid of amenities/price/bedrooms side-by-side.

## 6. Data Model

| Table | Key Columns |
|---|---|
| `property_favourites` | id, user_id (FK auth_users), property_id (FK properties), timestamps; unique (user_id, property_id) |
| `saved_searches` (Phase 2) | id, user_id, name, criteria (json), notify (bool), created_at |

Relationships: `auth_users` hasMany `property_favourites`; `properties` hasMany `property_favourites`.

## 7. Integrations & Dependencies

- Module 02 (property), Module 03 (marketplace cards), Module 15 (notifications for alerts), Module 16/17 (dashboard counts).

## 8. Acceptance Criteria

AC-01 Favouriting then unfavouriting leaves no duplicate rows.
AC-02 The tenant dashboard shows the correct favourite count and list.
AC-03 (Phase 2) A saved search triggers at most one notification per new match per tenant.

## 9. Implementation Status

- **Implemented (Wave 1):** FR-01, FR-02, FR-03, NFR-01, NFR-02, NFR-03, AC-01, AC-02.
- `POST tenant/favourites/{property}` toggles idempotently (`FavouriteService::toggle` → `favouritedProperties()->toggle()`, unique `(user_id, property_id)` enforces no dupes; cascade delete on property removal).
- Heart toggles live on marketplace cards (`favouriteIds` prop, optimistic + guest→login) and the public detail page (`isFavourited`); `GET tenant/favourites` renders `Tenant/Favourites` (thumbnail, price, type/beds/baths, available/unavailable grey-out, one-click remove). Demo tenant is seeded with one favourite in `PropertySeeder`.
- Tests: toggle on/off idempotent, never-duplicates, marketplace/detail/list state reporting, guest login redirect, owner 403 (in `tests/Feature/ListingAndDiscoverTest.php`).
- **Deferred (Phase 2):** FR-04 saved searches, FR-05 alerts (needs Notifications), FR-06 compare, AC-03.