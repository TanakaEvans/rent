# Module 03 - Property Marketplace / Search

> Phase: MVP | Implemented: full market-aligned filter set (type, zone/city/suburb, true min+max price range, bedrooms/bathrooms at-least, furnished/verified/availability/amenities AND-set, free-text `q`, geo `lat/lng/radius_km`), sorts (`newest`, `recently_updated`, `price_asc`, `price_desc`, `price_per_m2`, `featured`), featured-first ordering, pagination, URL-persistent live filters, hero search suggestions (`search.suggestions`), **property detail page** (gallery, leaflet map, financial breakdown, owner info with direct-contact CTAs, safety notice + report-listing modal, similar/recommended/recently-viewed rails, mobile sticky CTA), **saved searches + match counts + match alerts**, **listing lifecycle (validity, reminders, auto-expire, renew)**, **reports/moderation queue**, **owner + admin analytics**. Primary actors: Tenant (driver), Owner (publisher), Admin (config/moderation)

## 1. Purpose

The public, tenant-facing catalogue of available properties. Responsible for discovery, filtering, sorting and opening the property detail page. It is the top of the acquisition funnel for both tenants and owners.

> **Market benchmark:** The filter bar, result listing and browsing behaviour are modelled on Zimbabwe/South Africa rental portals reviewed in Sept 2026: classifieds.co.zw, propertybook.co.zw, property.co.zw, hararerentals.co.zw, seeff.co.zw, rentola.com/za and remax.co.za. Every filter below is one those portals actually expose; Dzimba implements a Zim-first subset of them.

## 2. Roles & Responsibilities

| Actor | Actions |
|---|---|
| **Tenant** | Browse, search, filter (type, location, beds, baths, price range min+max), sort, open details, view gallery, save favourites, enquire, apply. |
| **Owner** | Appears through their listings; benefits from search visibility and featured placement. |
| **Admin** | Configures locations/categories/amenities, curates featured placements, moderates listings shown. |

## 3. Functional Requirements

- FR-01 Public landing page (route `/`) renders listings without authentication.
- FR-02 Home page shows hero, filter bar, listing grid and "how it works" section.
- FR-03 Filters built from query parameters; grid re-renders via `router.get(route('home'), params, { preserveState })`. Filter state persists in the URL so results are shareable/bookmarkable.
- FR-04 Filter set (all optional, combinable): property type, location (city → zone/suburb), minimum & maximum price, bedrooms, bathrooms, furnished, and sort. Filters rendered as **quick-select chips** (e.g. beds "1+ to 5+", price tiers "≤$500 / ≤$1,000 / ≤$3,000") plus open min/max price inputs, mirroring the reviewed portals.
- FR-05 Live sandbox vs "search" split: the filter bar narrows results instantly (GET + `preserveState`); no separate submit button — matching classifieds.co.zw and hararerentals.co.zw behaviour.
- FR-06 Listing cards follow a consistent anatomy (see Card Anatomy below): cover image, price with per-month suffix, title, suburb/city, beds/baths, key amenities, badges.
- FR-07 Verified and featured badges render on cards; search scope is Harare residential first (see build plan note "one city first").
- FR-08 Detail page: photo gallery, description, amenities, map/location, owner info, enquiry/applying CTAs.
- FR-09 Status-aware: only `available` (and optionally `reserved` as "tenant applied") listings appear in public search.
- FR-10 Featured listings sort above standard listings.

## 4. Non-Functional Requirements

- NFR-01 Paginated results for large catalogues (MVP: home limit).
- NFR-02 Indexed columns: `status`, `city`, `price`, `property_type` (composite `(status, city)`); add `(status, price)` and `(status, beds)` when price/bedroom filtering is the common path.
- NFR-03 No auth required for browse; actions (favourite, enquire, apply) require login.
- NFR-04 SEO-friendly titles/meta on landing and detail pages (Phase 2).

## 5. Workflows & Pseudo Sentences

1. **Browse** - When a visitor opens `/`, the system loads featured listings first, then recent available listings; the system renders the hero and filter bar.
2. **Filter** - When a tenant selects city/house/3-bedroom/max $850, the system issues a GET request preserving page state; the system re-renders only the listing grid; the system updates the URL query string so results are shareable.
3. **Open detail** - When a tenant clicks a listing card, the system navigates to the property detail route; the system loads gallery, amenities and owner summary; when the tenant is logged in, the system shows Enquire and Apply buttons.
4. **Guard actions** - When a guest clicks Enquire or Apply, the system redirects to login; when login completes, the system returns the tenant to the property (intended URL).
5. **Featured placement** - When featured properties exist, the system promotes them above organic results; when the promotion expires, the system returns them to natural sort order.

## 6. Card Anatomy (from market review)

Listing cards follow the conventions of classifieds.co.zw / property.co.zw / hararerentals.co.zw / seeff.co.zw / rentola.co.zw:

1. **Image** — cover photo, first if the grid has other media indicators.
2. **Badges row** — featured/priority (top-left strip), verified ✔, "new" (last ≥7 days), all rendered as small pills.
3. **Price** — prominent, always with the per-month suffix, e.g. "$850 / month", exact money formatting `DECIMAL(12,2)` with a thousands separator.
4. **Title** — conventional form **"3 Bedroom House to Rent in Borrowdale"** = `{beds} Bedroom {Type} {in} {suburb|city}`. Classifieds use exactly this ("6 Bedroom House to Rent in Groom Bridge").
5. **Meta row #1 (specs)** — `beds` • `baths`, then area/size in m² (or acreage for land), e.g. "3 • 2 • 120 m²".
6. **Meta row #2 (location)** — suburb, zone, city, e.g. "Marlborough, Harare West, Harare" (propertybook.co.zw style) or lower-cased zone (classifieds style).
7. **Amenities strip** — 2-3 most relevant icons/labels (furnished, parking, borehole); truncate the rest, never overflow the card.
8. **Description snippet** — 1-2 lines clamp.
9. **Action icons** — favourite heart, share; only the heart is wired in MVP.
10. **Owner/agency block** — on card footer (latest from rentola/hararerentals): owner terms like "Verified Owner" with contact CTAs; desktop shows owner summary, mobile collapses.

## 7. Data Model

Uses `properties` (Module 02) plus lookup/config tables:

| Table | Role |
|---|---|
| `properties` | source of search results; indexes `(status, city)` |
| `system_settings` | site name, hero copy, currency, limits |
| `locations` (Phase 2) | city/suburb reference for consistent filters |
| `property_images` (Phase 2) | gallery for detail page |

## 8. Integrations & Dependencies

- Module 02 (listings), 04 (favourites), 05 (enquiries), 07 (applications), 14 (verification badge), 13 (featured).

## 9. Acceptance Criteria

AC-01 Unauthenticated visitor sees the marketplace and listings.
AC-02 Filters correctly narrow the grid and persist in the URL.
AC-03 Only available (published) listings appear.
AC-04 Featured and verified properties render their badges correctly.
AC-05 Card anatomy matches the market convention (price per-month, "N Bedroom Type in Suburb" title, beds/baths/area meta).
AC-06 Filter combination returns exactly the matching set — price is applied as a true min/max range, not a single threshold.

## 10. Implementation status

- **`app/Services/PropertySearchService.php`** owns the query: only `available` listings (`Property::listed`), combinable filters (type / city / zone / suburb / min+max price / bedrooms & bathrooms at-least / furnished), sort (`newest`, `recently_updated`, `price_asc`, `price_desc`) with **featured always first**, then `created_at`/`id` determinism; paginated 12 per page via `paginate()->withQueryString()`.
- **`app/Http/Controllers/HomeController.php`** normalises/whitelists query params (`normalizeFilters`) — invalid values (non-numeric price, unknown type) are ignored, never 500 — and passes `properties` (current page), `total`, `pagination`, `cities`, `zones`, `featured`, `justListed` (8 newest listings), `filters`.
- **Live filter bar (FR-05, no submit button):** selects fire `router.get(route('home'), params, { replace, preserveState, only })` immediately; min/max price inputs debounce 350ms; quick-select budget tiers `≤ $500 / ≤ $1,000 / ≤ $3,000` plus "Any". Filter state persists in the URL.
- Filter combos, true price range (AC-06), at-least beds/baths, invalid-value tolerance and pagination bounds are covered in `tests/Feature/ListingAndDiscoverTest.php`.
- **Property detail page (FR-08):** public `GET /properties/{id}` → `PublicPropertyController` + `PropertySearchService::findPublicDetail` (only `available`, eager-loads `images`/`owner`, 404 otherwise). `Marketplace/Show` renders gallery with thumbnails, specs, amenities, description, owner info block and Enquire/Apply CTAs (guests are routed to login). Marketplace cards + hero link to it. Tested: 200 public, 404 for reserved/occupied/unavailable/missing, owner info correct.
- **Refined marketplace UI (Sept 2026):** `Marketplace/Index` now matches the digital-premium spec — a **results toolbar** above the grid shows the live result count ("N properties found"), the sort control (moved out of the filter bar) and a **Grid/List view toggle** (`?view=grid|list` persists in the URL); a **"Just listed" horizontal strip** renders `justListed`; cards expose the full market anatomy (price with per-month suffix + deposit, title, location, type • beds • baths • area m² • furnishing via `SpecItem`, **"Listed directly by the owner"** trust line `TrustFeature`, `AvailabilityLine` "Available now / from {date}", `timeAgo` listing freshness, New/Verified/Featured badges); a **Quick View slide-in panel** (`QuickViewPanel`) shows price, deposit, stats grid, amenities and save/full-details CTAs; a slim **trust banner** under the filter bar restates the no-agent-fees + "never pay before viewing" guidance, shared with the enquiries flow); the empty state offers one-tap relaxations (clear all / lower budget / any location) when filters are active. Favourite toggling and pagination behaviour are unchanged.

- **Search engine additions (Sept 2026):** free-text `q` (title/suburb/city/zone/description), `verified` and `furnished` booleans, `availability=now|upcoming` (via `available_from`), `amenities` (ALL-must-be-present AND set, matched at the serialised-JSON level for SQLite/MySQL portability), geo **`lat` + `lng` + `radius_km`** coarse bounding with `deltaLat = radius/111` (exact ordering stays client-side on the map), and the **`price_per_m2`** sort (`price / building_size` such that south-facing tiny units rank fairly); unknown/invalid filter values are ignored, never 500.
- **Hero search suggestions (FR-09, `search.suggestions`):** JSON autocomplete over listed suburbs/cities/zones (jump into a filtered search) and property titles (jump to the detail page), capped 6 by default.
- **Property detail page (FR-08, Sept 2026 rebuild):** `Marketplace/Show.jsx` implements the discovery-spec anatomy — image gallery with prev/next + counter + thumbnails, leaflet map with a styled price-pin popup (`.dz-map-pin`/`.dz-map-pop` in `resources/css/app.css`; scroll-zoom disabled; map only when `lat`/`lng` are valid, otherwise a "location shared on enquiry" fallback), specs grid, financial breakdown (rent + deposit + move-in total), owner preview ("Listed directly by the owner", Verified Owner badge, member since, reply email), Enquire form + Book-a-viewing slot picker + Apply section, favourite toggle, safety notice ("never pay before viewing"), and a **report-listing modal** posting to `property.report` with config-driven categories.
- **Saved searches (module 04 + §47):** `SavedSearchService` (create/update/delete scoped per tenant, 404 on foreign rows) + `matches()` reusable by the alert pass; `tenant.saved-searches.*` routes render `Tenant/SavedSearches` with a live `match_count` per row (re-searched per_page=1). Alert-enabled searches receive match notifications via `MarketplaceAlertService` (24 h dedupe).
- **Listing lifecycle (§41/§42):** `listings.validity_days` stamps `expires_at` on publish; `marketplace:housekeeping` auto-expires overdue listings (state `available→unavailable` + history "Listing expired") and sends `ListingExpiryReminderNotification` per `listings.validity_reminders`; `owner.properties.renew` re-lists within `listings.renew_grace_days` (quota-gated).
- **Reports & moderation (§35):** `reports` table + explicit `Report` state machine (`open↔under_review↔escalated`, `resolved` terminal, `dismissed` reopenable); `ReportService::transition` records the moderator/resolution note and can take a reported available listing down with history; admin queue `admin.marketplace.reports.{index,transition}` with status/priority filters + counts; `Tenant/Reports` lists the reporter's own reports only.
- **Analytics (§43/§44):** `MarketplaceAnalyticsService` drives `Owner/Analytics` (owner-scoped, per-property views/favourites/enquiries/applications, 30-day trend, top property) and `Admin/Marketplace/Analytics` (platform totals, averages, trend, most-viewed, top areas).
- Test coverage: `ListingAndDiscoverTest` (47) + new `TrustGovernTest` (11) cover search/radius/price-per-m²/suggestions, saved-search CRUD + match counts + cross-tenant 404, lifecycle expiry/reminder/renew, report validation + state machine + hide-listing, analytics scoping; `DzimbaAccessControlTest` (95) covers the role matrix for every new route. Full suite 295 passed / 1457 assertions.