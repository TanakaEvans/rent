# Property Marketplace

## Premium Listings & Property Discovery Experience

### 1. Design Vision

The Listings page must not look like a traditional classified-ads website.

It should feel:

* Premium
* Modern
* Fast
* Trustworthy
* Visual
* Intelligent
* Easy to navigate
* Mobile-first
* Data-rich without feeling cluttered

The primary goal is to help a tenant answer five questions immediately:

1. **Where is the property?**
2. **What does it look like?**
3. **How much does it cost?**
4. **Can I trust the listing/owner?**
5. **What can I do next?**

The entire experience should encourage:

**Discover → Compare → Inspect → Contact → Apply → Rent**

---

# 2. Main Listings Page Layout

The recommended desktop layout:

```text
┌──────────────────────────────────────────────────────────────┐
│ LOGO     Buy/Rent     Locations     Services      Login      │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│        Find a place you'll love to call home                 │
│                                                              │
│  [ Location ] [ Property Type ] [ Price ] [ Beds ] [ Search ]│
│                                                              │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│  1,248 properties found                    Sort: Recommended  │
│                                                              │
│  [ Filters ]                         [ Grid ] [ List ] [ Map ]│
│                                                              │
│  ┌───────────────┐ ┌───────────────┐ ┌───────────────┐       │
│  │               │ │               │ │               │       │
│  │    IMAGE      │ │    IMAGE      │ │    IMAGE      │       │
│  │               │ │               │ │               │       │
│  │ ♥       ✓     │ │ ♥             │ │ ♥       ★     │       │
│  ├───────────────┤ ├───────────────┤ ├───────────────┤       │
│  │ $450 / month  │ │ $650 / month  │ │ $800 / month  │       │
│  │ 3 Bed • 2 Bath│ │ 4 Bed • 3 Bath│ │ 4 Bed • 2 Bath│       │
│  │ Avondale      │ │ Borrowdale    │ │ Greendale     │       │
│  │               │ │               │ │               │       │
│  │ ✓ Verified    │ │ ✓ Verified    │ │ ★ Featured    │       │
│  │ Owner listed  │ │ Owner listed  │ │ ✓ Verified    │       │
│  └───────────────┘ └───────────────┘ └───────────────┘       │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

---

# 3. Hero Search Experience

Do not immediately throw the user into a wall of property cards.

The top of the page should have a strong search experience.

### Primary search bar

The search component should support:

**Where**

* City
* Suburb
* Area
* Landmark
* Street
* Property ID

**What**

* Apartment
* House
* Cottage
* Townhouse
* Office
* Commercial
* Land
* Other configurable property types

**Budget**

* Minimum
* Maximum

**Bedrooms**

* Studio
* 1+
* 2+
* 3+
* 4+
* 5+

**More Filters**

Open a powerful filter panel.

### Search should feel intelligent

Examples:

> "3 bedroom house in Borrowdale under $900"

or

> "2 bedroom apartment near CBD"

The platform should eventually support natural-language property search.

---

# 4. Filter System

Filters are one of the most important parts of the page.

Do not expose 30 filters immediately.

Use:

### Primary filters

* Location
* Property Type
* Price
* Bedrooms
* Bathrooms
* Furnished
* Availability
* Verified

### Advanced filters

**Property**

* Property type
* Bedrooms
* Bathrooms
* Floor area
* Lot size
* Furnished
* Newly listed
* Available date

**Amenities**

* Parking
* Borehole
* Solar
* Backup power
* Water
* Internet
* Garden
* Pool
* Security
* Gated community
* Air conditioning
* Built-in cupboards
* Ensuite
* Balcony
* Pet friendly

Amenities must be configurable by administrators.

Do not hard-code the amenity list.

---

# 5. Location Filters

Location should be hierarchical.

```text
Country
   ↓
Province
   ↓
City
   ↓
Suburb
   ↓
Area
```

The system should also support:

* Map search
* Search within radius
* Near landmark
* Near school
* Near shopping centre
* Near workplace
* Near transport

Example:

> Within 5 km of Harare CBD

---

# 6. Map + Listings Mode

This should be one of the platform's standout features.

Provide:

**Grid | List | Map**

The map mode should display property markers.

Example:

```text
┌──────────────────────┬───────────────────────────────┐
│                      │                               │
│  PROPERTY LIST       │             MAP               │
│                      │                               │
│  $450                 │        ● $450                 │
│  3 bed • Avondale    │                 ● $650        │
│                      │                               │
│  $650                 │    ● $500                    │
│  4 bed • Borrowdale  │                       ● $900  │
│                      │                               │
│  $500                 │                               │
│  2 bed • Mt Pleasant │                               │
│                      │                               │
└──────────────────────┴───────────────────────────────┘
```

Clicking a marker should highlight the corresponding listing.

Clicking a property should highlight its location.

On mobile, allow:

**List → Map**

without requiring the user to reload the page.

---

# 7. Property Card Design

The property card is the most important visual component.

It should NOT resemble a generic Bootstrap card.

### Card structure

```text
┌──────────────────────────────┐
│                              │
│                              │
│        PROPERTY IMAGE        │
│                              │
│  ♥                     ✓     │
│                              │
│  3 / 12                      │
├──────────────────────────────┤
│ $650 / month                 │
│                              │
│ 3 beds • 2 baths • 180 m²    │
│                              │
│ Modern Family Home           │
│ Borrowdale, Harare           │
│                              │
│ ✓ Verified Property          │
│ ✓ Verified Owner             │
│                              │
│ Owner listed directly        │
└──────────────────────────────┘
```

---

# 8. Property Image Experience

Images should dominate the card.

Use a high-quality image carousel.

Features:

* Swipe
* Arrow navigation
* Image counter
* Lazy loading
* Full-screen preview
* Video support
* Virtual tour support
* Image zoom

Example:

**1 / 18**

should be visible.

Avoid tiny image thumbnails that make the property look cheap.

---

# 9. Trust Badges

This platform's biggest competitive advantage is eliminating unnecessary agents.

Therefore trust needs to become a major part of the UI.

Possible badges:

### ✓ Verified Owner

Owner identity/details have been verified.

### ✓ Verified Property

Property information/documents have been reviewed.

### ✓ Owner Listed

The property was submitted directly by the owner.

### ★ Featured

Paid promotional listing.

### ⚡ New

Recently published.

### 🔥 Popular

High engagement.

### 🏆 Top Rated

Based on configured reviews/ratings.

Badges must be configurable from the Admin system.

---

# 10. "Direct Owner" Differentiator

This should be a major part of the experience.

Instead of merely saying:

> Contact Agent

show:

> **Listed directly by the owner**

Then:

**No traditional agent listing fee**

and:

**Contact owner directly**

This immediately communicates what makes the platform different.

---

# 11. Price Presentation

Price needs to be extremely clear.

Example:

### $650

**/ month**

Then optionally:

* Deposit: $1,300
* Utilities: Excluded
* Service charge: $50
* Available: 1 Oct 2026

The system should calculate and display configured costs.

Do not make tenants discover important costs after contacting the owner.

---

# 12. "True Monthly Cost"

A premium feature would be:

### Estimated monthly cost

```text
Rent                 $650
Service charge        $50
Estimated utilities   $70
────────────────────────
Estimated total      $770
```

Where information is available.

This creates significantly more transparency than normal property sites.

---

# 13. Listing Quality Indicator

Introduce a listing-quality score.

For example:

### Listing completeness

**92% complete**

Based on:

* Photos
* Description
* Location
* Pricing
* Amenities
* Availability
* Property details
* Owner verification
* Documents

This encourages owners to create better listings.

---

# 14. Property Availability

Every listing should clearly show:

**Available now**

or

**Available from 1 October 2026**

Possible statuses:

* Available now
* Available soon
* Occupied
* Reserved
* Under application
* Let
* Temporarily unavailable

The statuses should be configurable.

---

# 15. Freshness

Show:

> Listed 2 days ago

or:

> Updated yesterday

Users should be able to filter:

**Newest listings**

This is important because property availability changes quickly.

---

# 16. Save / Favourite

Every card should have:

♡ Save

When saved:

♥ Saved

Users should have:

### Saved Properties

with:

* Property
* Price
* Availability
* Last updated
* Price changes
* Status changes

---

# 17. Price Drop Notifications

This is an excellent premium feature.

If a saved property changes:

> **Price reduced**

or:

> **Availability changed**

notify the tenant.

Admin should configure:

* Email
* SMS
* WhatsApp
* In-app notification

---

# 18. Compare Properties

Allow users to select multiple properties.

Example:

```text
☑ Property A
☑ Property B
☐ Property C

[ Compare 2 Properties ]
```

Comparison:

| Feature   | Property A | Property B |
| --------- | ---------: | ---------: |
| Rent      |       $650 |       $700 |
| Bedrooms  |          3 |          4 |
| Bathrooms |          2 |          3 |
| Area      |      180m² |      220m² |
| Parking   |          ✓ |          ✓ |
| Solar     |          ✓ |          — |
| Borehole  |          ✓ |          ✓ |
| Verified  |          ✓ |          ✓ |

This should be a major differentiator.

---

# 19. Quick Preview

Do not force the user to open every property page.

Hover/click:

**Quick View**

opens a side panel containing:

* Main image
* Price
* Location
* Beds
* Baths
* Key amenities
* Verification
* Availability
* Owner status
* Save
* Compare
* Contact owner
* Request viewing

This makes browsing extremely fast.

---

# 20. Intelligent Sorting

Sorting should go beyond:

* Price low → high
* Price high → low
* Newest

Include:

### Recommended

Personalized based on:

* Search criteria
* Previous views
* Favourites
* Location
* Budget
* Property type
* Engagement
* Availability

Other options:

* Newest
* Lowest price
* Highest price
* Most relevant
* Most viewed
* Recently updated
* Nearest
* Price per m²
* Featured

The recommendation algorithm should be configurable.

---

# 21. Featured Listings

Featured listings should look premium but not misleading.

Use subtle placement:

**Featured**

or

**Sponsored**

Do not make paid listings indistinguishable from normal listings.

Possible placement:

* Top row
* Featured carousel
* Search priority
* Location-specific promotion

Admin controls:

* Featured price
* Duration
* Placement
* Maximum slots
* Priority
* Auto expiry

---

# 22. "Just Added" Section

At the top of the marketplace:

### Just Listed

A horizontal carousel showing recently published properties.

Example:

> Just listed in Borrowdale

This creates a feeling that the marketplace is active.

---

# 23. "Perfect For You"

For logged-in tenants:

### Recommended for you

Based on:

* Saved searches
* Previous properties viewed
* Budget
* Location
* Bedrooms
* Amenities

Example:

> We found 8 properties matching your preferences.

This can eventually become an AI-powered recommendation system.

---

# 24. Neighborhood Discovery

Do not only sell individual houses.

Sell locations.

Example:

### Explore Harare

```text
Borrowdale
[Image]
142 properties

Avondale
[Image]
86 properties

Mount Pleasant
[Image]
64 properties

Greendale
[Image]
51 properties
```

Each location should have:

* Average rental price
* Number of properties
* Property types
* Popular amenities
* Map
* Nearby facilities

---

# 25. Search Suggestions

As users type:

```text
Search for a location...

Borrowdale
  142 properties

Borrowdale Brooke
  32 properties

Borrowdale West
  24 properties
```

Also suggest:

* Recent searches
* Saved searches
* Popular locations

---

# 26. Smart Empty States

Never show:

> No results found.

Instead:

### We couldn't find an exact match.

Then automatically suggest:

* Increase budget
* Expand location
* Remove one filter
* Similar properties

Example:

> No 3-bedroom properties under $500 found in Borrowdale.

> Try these 12 properties between $500–$650.

This keeps users inside the platform.

---

# 27. Search Persistence

When a user navigates into a property and comes back:

**Their search must remain intact.**

Preserve:

* Filters
* Location
* Sort
* Page
* Map position
* View mode

This is a small feature that makes the platform feel significantly more polished.

---

# 28. Mobile Experience

Mobile should NOT simply be a shrunken desktop page.

Recommended mobile layout:

```text
┌──────────────────────┐
│ ☰  LOGO       ♡ 👤  │
├──────────────────────┤
│ 🔎 Where?             │
│                      │
│ [Filters] [Sort]     │
├──────────────────────┤
│                      │
│      PROPERTY        │
│       IMAGE          │
│                      │
│ ♥              ✓    │
├──────────────────────┤
│ $650 / month         │
│ 3 bed • 2 bath       │
│ Borrowdale           │
│ ✓ Verified Owner     │
└──────────────────────┘
```

Bottom navigation can contain:

**Search | Saved | Messages | Applications | Account**

---

# 29. Sticky Mobile Actions

When viewing a property:

```text
┌────────────────────────────┐
│                            │
│      PROPERTY DETAILS      │
│                            │
│                            │
├────────────────────────────┤
│ [ Contact Owner ] [ Apply ]│
└────────────────────────────┘
```

The action bar stays accessible.

---

# 30. Property Detail Transition

Clicking a card should feel seamless.

The property page should open with:

1. Large image gallery
2. Price
3. Property summary
4. Verification
5. Location
6. Description
7. Amenities
8. Financial breakdown
9. Availability
10. Owner
11. Viewing
12. Application
13. Similar properties

The listing page and property page must feel like one continuous product.

---

# 31. Owner Profile Preview

Show:

### Listed by

**John M.**

✓ Verified Owner

**12 properties**

**Member since 2025**

**Response rate: 94%**

**Usually responds within 2 hours**

Do not expose unnecessary private information.

The objective is to increase trust.

---

# 32. Response Speed

A very useful marketplace metric:

> **Usually responds within 2 hours**

Possible values:

* Usually responds within minutes
* Usually responds within 1 hour
* Usually responds within 24 hours

Calculated from actual platform activity.

---

# 33. Viewing Availability

Instead of:

> Contact owner for viewing.

Allow:

### Request a viewing

Select:

**Date**

**Time**

**Number of people**

Then:

**Request Viewing**

The owner receives the request.

This makes the platform feel much more advanced.

---

# 34. One-Tap Contact

Primary CTA:

### Contact Owner

Secondary:

### Request Viewing

Third:

### Apply Now

Depending on configuration and property status.

Contact options may include:

* Platform messaging
* Phone
* WhatsApp
* Email

Admin should control which contact methods are enabled.

---

# 35. Anti-Scam / Safety UX

This is extremely important for a direct-owner marketplace.

Display:

### Platform safety notice

> Never send money before verifying the property and agreement.

Allow users to:

**Report listing**

Reasons:

* Suspicious listing
* Incorrect information
* Duplicate
* Wrong price
* Fraud concern
* Already rented
* Inappropriate content

These reports go into the Admin moderation queue.

---

# 36. Listing Verification System

Every property should have an internal verification state.

Example:

```text
DRAFT
   ↓
SUBMITTED
   ↓
UNDER REVIEW
   ↓
VERIFIED
   ↓
PUBLISHED
```

Possible rejection:

```text
REJECTED
   ↓
CORRECTION REQUIRED
   ↓
RESUBMITTED
```

Admins must be able to review:

* Owner
* Property information
* Images
* Documents
* Location
* Pricing
* Ownership information

---

# 37. Dynamic Property Attributes

This is essential for your ERP architecture.

Do not hard-code every field.

Admin should be able to configure:

### Property Types

* Apartment
* House
* Office
* Warehouse
* Retail
* Land
* Lodge
* Other

### Custom Attributes

Admin can create:

**Attribute:** Solar System

Type:

**Yes/No**

or:

**Attribute:** Floor Area

Type:

**Number**

or:

**Attribute:** Parking Spaces

Type:

**Number**

or:

**Attribute:** Security Type

Type:

**Dropdown**

This means the platform can evolve without developers constantly changing the database/UI.

---

# 38. Dynamic Listing Sections

Admin should be able to configure which sections appear.

For example:

```text
Property Overview
Amenities
Utilities
Security
Location
Financial Information
Availability
Owner Information
Documents
Nearby Places
Viewing
Application
```

Admin can:

* Enable
* Disable
* Reorder
* Rename
* Make required
* Make optional

---

# 39. Dynamic Search Filters

This should also be administrator-controlled.

Example:

Admin creates:

**Solar**

Category:

Utilities

Filter:

Yes / No

It automatically becomes available as a marketplace filter.

This is a major architectural requirement.

---

# 40. Listing Quality Rules

The admin should define:

```text
Minimum photos: 5
Minimum description: 100 characters
Location required: YES
Price required: YES
Bedrooms required: YES
Owner verification required: YES
Property verification required: YES
```

The platform automatically enforces these rules.

---

# 41. Listing Expiration

Listings should not remain online forever.

Admin configuration:

```text
Listing validity: 60 days
Reminder: 14 days before expiry
Reminder: 7 days before expiry
Reminder: 1 day before expiry
Auto-expire: YES
```

Owner can:

**Renew Listing**

if allowed.

---

# 42. Automatic Listing Status

The platform should automatically update status.

Example:

```text
Published
     ↓
Available
     ↓
Viewing Scheduled
     ↓
Application Received
     ↓
Reserved
     ↓
Rented
     ↓
Archived
```

The owner should not have to manually manage everything.

---

# 43. Analytics on Every Listing

Owners should see:

### Property performance

```text
Views              1,284
Saves                86
Enquiries            32
Viewings              9
Applications          4
```

Additional:

* Search impressions
* Click-through rate
* Average time viewed
* Contact conversion
* Viewing conversion
* Application conversion

This gives landlords a reason to remain subscribed.

---

# 44. Admin Marketplace Analytics

Admin should see:

### Marketplace Overview

* Total listings
* Active listings
* New listings today
* New listings this month
* Available properties
* Rented properties
* Pending approvals
* Reported properties
* Featured listings
* Most searched locations
* Most searched property types
* Average rental price
* Price trends
* Listing conversion

---

# 45. Intelligent Recommendations

Eventually introduce:

### Similar properties

Based on:

* Price
* Location
* Property type
* Bedrooms
* Amenities
* Size

Example:

> Similar properties you may like

This should appear:

* Below property details
* At the bottom of search
* After saving a property
* After viewing several properties

---

# 46. Recently Viewed

Logged-in users:

### Recently Viewed

```text
Recently viewed

Borrowdale — $650
Avondale — $550
Mount Pleasant — $700
```

This should be easily accessible.

---

# 47. Saved Searches

Allow:

> Save this search

Example:

**"3 Bedroom Houses in Borrowdale under $900"**

Then configure:

* Daily alerts
* Instant alerts
* Weekly summary

Notifications can be:

* In-app
* Email
* SMS
* WhatsApp

---

# 48. Search Result Personalization

For logged-in users, the page can eventually say:

> Good evening, Tanaka

> 18 new properties match your saved searches.

Then show:

### New matches for you

This creates a much more personal marketplace experience.

---

# 49. Performance Requirements

The Listings page must be extremely fast.

Target:

* Initial page load: ideally under 2–3 seconds
* Images lazy-loaded
* Responsive image sizes
* Pagination/infinite scrolling
* Server-side filtering
* Indexed database queries
* Cached location data
* Cached popular searches
* Skeleton loaders

Never load every property into the browser.

---

# 50. Skeleton Loading

Instead of blank screens:

```text
┌───────────────┐
│ ░░░░░░░░░░░░  │
│ ░░░░░░░░░░░░  │
├───────────────┤
│ ░░░░░░░░       │
│ ░░░░░░░        │
│ ░░░░░          │
└───────────────┘
```

The interface should feel instant while data loads.

---

# 51. Premium Visual Language

Avoid:

* Excessive borders
* Heavy shadows
* Too many colors
* Tiny text
* Cheap gradients
* Crowded cards
* Huge buttons everywhere

Use:

* Large photography
* Generous whitespace
* Strong typography
* Subtle borders
* Soft shadows
* Rounded corners
* Clear hierarchy
* Restrained accent color
* Smooth micro-interactions

The platform should look expensive without being visually noisy.

---

# 52. Micro-interactions

Use subtle animations:

* Favourite heart animation
* Image transitions
* Filter drawer
* Map marker selection
* Card hover
* Save confirmation
* Compare selection
* Skeleton loading
* Toast notifications
* Smooth page transitions

Animations should be fast and subtle.

---

# 53. Marketplace Sections

The complete marketplace homepage/listings experience can contain:

### Hero Search

↓

### Featured Properties

↓

### Just Listed

↓

### Recommended For You

↓

### Explore Locations

↓

### Browse By Property Type

↓

### Properties Near You

↓

### Popular Properties

↓

### Recently Viewed

↓

### Saved Search Matches

↓

### All Properties

This gives the marketplace much more depth than simply displaying a grid.

---

# 54. Admin Control Over Marketplace

Everything important should be configurable.

Admin should be able to configure:

### Marketplace

* Listings per page
* Default view
* Default sorting
* Featured position
* Recommended algorithm
* Search behaviour
* Map enabled
* Compare enabled
* Quick view enabled
* Saved searches enabled
* Recently viewed enabled

### Cards

* Fields shown
* Badges shown
* Price format
* Image count
* Amenities shown
* Owner information
* Verification badges

### Search

* Available filters
* Filter order
* Required filters
* Search radius
* Sort options
* Search suggestions

### Listing

* Required fields
* Required photos
* Listing duration
* Approval rules
* Verification requirements
* Expiry rules

### Monetization

* Featured listing prices
* Promotion duration
* Property boosts
* Premium placement
* Subscription requirements

---

# 55. Marketplace Configuration Hierarchy

The system should support:

```text
GLOBAL SETTINGS
       ↓
USER TYPE SETTINGS
       ↓
SUBSCRIPTION PLAN
       ↓
BRANCH / LOCATION
       ↓
PROPERTY TYPE
       ↓
INDIVIDUAL PROPERTY
```

More specific configuration overrides broader configuration.

For example:

```text
Global listing validity = 60 days

Commercial properties = 90 days

Warehouse properties = 120 days
```

The system automatically uses the correct rule.

---

# 56. "Why This Property?" Feature

This could become one of the platform's signature features.

Instead of simply showing:

> Recommended

show:

### Why this property?

> ✓ Within your budget
> ✓ Matches your 3-bedroom preference
> ✓ In your preferred area
> ✓ Has parking
> ✓ Has solar backup
> ✓ Recently listed

This makes recommendations understandable rather than mysterious.

---

# 57. Property Match Score

For logged-in users:

### 94% Match

Based on configured criteria.

Example:

```text
Budget       ✓
Location     ✓
Bedrooms     ✓
Bathrooms    ✓
Amenities    ✓
Availability ✓
```

Again, this should be configurable.

---

# 58. "No Agent Fee" Positioning

This should appear throughout the marketplace without becoming annoying.

Examples:

**Direct from owner**

**No traditional agent involved**

**Connect directly with the property owner**

This becomes part of the platform's brand identity.

---

# 59. The Ultimate Listing Card

The final card should communicate almost everything important in 3 seconds:

```text
┌────────────────────────────────────┐
│                                    │
│            LARGE IMAGE             │
│                                    │
│  ♥                           ✓     │
│  FEATURED                          │
│                           5 / 18   │
├────────────────────────────────────┤
│                                    │
│ $850 / month                       │
│                                    │
│ 4 beds   •   3 baths   •   220m²  │
│                                    │
│ Modern 4 Bedroom Family Home       │
│ Borrowdale, Harare                 │
│                                    │
│ ✓ Verified Property                │
│ ✓ Verified Owner                   │
│                                    │
│ Listed directly by owner           │
│                                    │
│ [ Quick View ]                     │
└────────────────────────────────────┘
```

---

# 60. The Three Core Differentiators

The listings experience should ultimately be built around three things:

## 1. Discovery

Make finding the right property incredibly easy.

Search, filters, map, recommendations, saved searches and personalization.

## 2. Trust

Make tenants confident.

Verified owner, verified property, listing freshness, transparent pricing, safety tools and owner response information.

## 3. Action

Remove friction.

Contact owner → request viewing → apply → agreement.

The user should never feel lost about what to do next.

---

# 61. Recommended MVP vs Future Features

### Phase 1 — Must Have

* Premium listing cards
* Powerful search
* Filters
* Sorting
* Property images
* Favourites
* Verification badges
* Owner information
* Property detail page
* Contact owner
* Viewing request
* Application
* Map
* Responsive mobile design
* Admin moderation

### Phase 2 — Competitive Advantage

* Property comparison
* Quick View
* Saved searches
* Notifications
* Price-drop alerts
* Recently viewed
* Owner response metrics
* Listing quality score
* Advanced map search
* Featured listings
* Property analytics

### Phase 3 — Market Leader

* Personalized recommendations
* Property Match Score
* "Why this property?"
* Natural-language search
* AI recommendations
* Neighborhood intelligence
* Price intelligence
* Rental price trends
* Smart property ranking
* Predictive availability
* Intelligent tenant/property matching

---

# 62. Final Design Principle

The platform should **not feel like a website containing property listings**.

It should feel like a **property discovery and rental operating system**.

A tenant should be able to:

**Search → Discover → Compare → Verify → Contact → View → Apply → Sign → Pay**

without being pushed toward a traditional property agent.

At the same time, the landlord should see:

**List → Promote → Receive enquiries → Schedule viewings → Review applications → Rent → Manage**

and the administrator should have complete control over the rules governing both experiences.

That is what will make the platform significantly more sophisticated than a normal property marketplace.

---

# 63. Build Log (Sept 2026)

The sections below were implemented end-to-end as the "Property Marketplace" batch (B1-B6) in September 2026. Each batch was a vertical slice: migration → model → service → controller/route → Inertia page → shared component → feature + access-control tests.

## 63.1 Search engine (B1-B4) — DONE

- `PropertySearchService` owns the public query: only `available` listings, combinable filters `property_type`, `city`, `zone`, `suburb`, true min+max `price`, at-least `bedrooms`/`bathrooms`, `furnished`, `verified`, `availability` (now/upcoming), `amenities` (AND semantics via portable JSON `LIKE`), free-text `q` (title/suburb/city/zone/description), geo `lat`/`lng`/`radius_km` coarse bounding, sorts `newest` | `recently_updated` | `price_asc` | `price_desc` | **`price_per_m2`** | `featured` (featured always ranks first), paginated via `withQueryString()`.
- `HomeController` whitelists/normalises query params (invalid values ignored, never 500), computes `featured`/`justListed`/`explore`/`recommended`/`recentlyViewed` sections, and serves the `search.suggestions` JSON autocomplete (suburbs/cities/zones/titles).
- Marketplace `Index.jsx` (market card anatomy, hero search, live filter bar + budget tiers, grid/list toggle, suggested searches, leaflet map with `dz-map-pin`/`dz-map-pop` marker styles, Quick View panel, trust banner, smart empty states), `Build` green.

## 63.2 Property detail experience (B5) — DONE

- `Marketplace/Show.jsx` rebuilt to the §30-§36 spec: gallery with counter/arrows/thumbnails, price + deposit + availability, specs grid, financial breakdown (rent / deposit / move-in), leaflet map (price pin + popup, disabled scroll-zoom), owner preview ("Listed directly by the owner", Verified Owner badge, member since, email), one-tap Enquire + "Book a viewing" (slots) + Apply (application state), favourite toggle, safety notice card, "Report listing" modal (categories from config), similar/recommended/recently-viewed rails, and a mobile sticky CTA bar anchoring to `#contact`.
- `PublicPropertyController`/`findPublicDetail` eager-load images + owner (`id, name, email, verified, created_at`); 404 unless `available`.

## 63.3 Trust, saved searches, lifecycle & analytics (B3 + B6) — DONE

- **Reports/moderation**: `reports` table + `Report` explicit state machine (`open → under_review/resolved/dismissed`, `under_review → resolved/escalated/dismissed`, `escalated → under_review/resolved`, `dismissed → open`, `resolved` terminal) + `ReportService` (public create incl. guests, reporter list, admin queue + status/priority filters + stats, transition with moderator/resolution note and optional `hide_listing` that takes a reported available property down with history). Routes: public `property.report`, `tenant.reports.index`, `admin.marketplace.reports.{index,transition}` (`admin.` name prefix). Pages: `Tenant/Reports`, `Admin/Marketplace/Reports` (queue table, filter selects, transition buttons with note + hide-listing prompt, StatCard counts).
- **Saved searches**: `saved_searches` + `SavedSearchService` (create/list per tenant/match-count/update/delete, `matches()` + `alertable()` for the alert pass) + `SavedSearchController` (4 tenant routes). `Tenant/SavedSearches` page: run / rename / notify toggle / delete.
- **Alerts** (daily/instant, deduped <24h): `SavedSearchMatchNotification` on new match, `PriceDropNotification` on favourited price drops, `AvailabilityAlertNotification` on back-on-market; orchestrator `MarketplaceAlertService`.
- **Listing lifecycle**: `listings.validity_days`/`validity_reminders`/`auto_expire`/`renew_grace_days` config → `ListingLifecycleService` (`expireDue`, `dueSoon`, `renew` with quota + grace guard) + `marketplace:housekeeping` command + `ListingExpiryReminderNotification`; `PropertyService` stamps `expires_at` on publish.
- **Analytics**: `MarketplaceAnalyticsService` → `Owner/Analytics` (per-property views/favourites/enquiries/applications, 30-day trend, top property — owner-scoped) and `Admin/Marketplace/Analytics` (platform totals, price averages, 30-day trend, most-viewed, top areas).

## 63.4 Fixes landed in the batch

- `PropertyHistory` gained a `note` column (migration `2026_09_09_000025`) + fillable — status-change notes (expire / renew / report-takedown) were previously silently dropped.
- `ReportService::create` no longer triggers "Undefined array key priority" when a report is filed without a priority.
- Admin pages use the real `admin.marketplace.*` route names (the admin group's to `admin.` prefix) — the `marketplace.*` names do not exist and `route()` would throw.
- `Tenant/Leases` was linking to a non-existent `marketplace.show` route — now `property.show`.

## 63.5 Test coverage (green)

- `ListingAndDiscoverTest` (47): all prior marketplace tests + keyword search, radius filter, price-per-m² sort, suggestions, saved-search CRUD + match counts + cross-tenant 404, lifecycle expiry/reminder/renew + grace-window guard.
- `TrustGovernTest` (11, new suite): public report, validation + invalid category, missing-property 404, tenant own-reports isolation, admin queue stats, resolve-with-note, explicit state machine, hide-listing takedown, owner analytics scoping (own-only), admin platform analytics.
- `DzimbaAccessControlTest` (95): matrix extended for saved-searches, tenant reports, owner analytics, admin marketplace analytics/reports queue/transition, and guest public reporting.
- **Full suite: 295 passed / 1457 assertions; `npm run build` clean; `migrate:fresh --seed` verified; `route:list` clean.**
