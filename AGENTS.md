# Dzimba - Global Working Rules (MANDATORY)

These rules apply to every task. The original product concept notes are preserved below the separator at the end of this file.

## 1. Context

Dzimba is a direct property-rental marketplace + property-management ERP (Laravel + Inertia + React + Tailwind v4, MySQL dev / SQLite tests). Three parties drive it: **Admin**, **Property Owner**, **Tenant** (a Contractor actor appears in Phase 2). Full module docs: `docs/modules/`.

### Sources of truth (read these first, in order)

1. **`docs/modules/23-implementation-plan.md` §1.5** — the status tracker. States the current wave, which feature is NEXT UP, what is in flight, and what is done. **This determines what you build. Never pick work from anywhere else.**
2. **`docs/modules/23-implementation-plan.md`** — full build order, wave coupling, per-feature vertical steps and their required tests.
3. **`docs/modules/21-database-design.md`** — the full ERD and every table's columns (implemented + planned).
4. **`docs/modules/03-marketplace-search.md` / `02-property-management.md`** — product specs (marketplace filter set, card anatomy, property taxonomy).
5. **`docs/06-coding-standards.md`, `docs/03-architecture-and-data.md`** — backend/frontend rules.

### Where the files live (map)

- **DB migrations:** `database/migrations/` (core Dzimba tables are dated `2024_/2025_/2026_09_09_*`).
- **Models:** `app/Models/` (business logic lives in services, not models).
- **Services:** `app/Services/` (search for the existing `PropertyService` pattern).
- **Controllers:** `app/Http/Controllers/`.
- **Routes:** `routes/web.php` (role groups: `admin`, `role:Owner`, `role:Tenant`).
- **Frontend pages:** `resources/js/Pages/` (owned: `Pages/Owner/*`; tenant: `Pages/Tenant/*`; admin: `Pages/Admin/*`; public marketplace: `Pages/Marketplace/*`).
- **Shared UI:** `resources/js/Components/Shared/*` (StatusBadge, StatCard, Brand, PropertyArt, EmptyState) and `resources/js/Components/ui/*` (shadcn kit).
- **Layouts:** `resources/js/Layouts/MainLayout.jsx` (marketplace/role) and `AdminLayout.jsx` (admin).
- **Seeders:** `database/seeders/` (demo users per §6 below).
- **Tests:** `tests/Feature/DzimbaAccessControlTest.php` (role matrix — add every new role-gated route here), feature suites `ListingAndDiscoverTest`, `EngageTest`, `CommitTest`, `MonetiseTest`, `OperateTest`, `TrustGovernTest` as the waves land.

## 2. Development discipline - no loose ends, no unsystematic behaviour

- **Vertical end-to-end slices only.** Every feature is cut through the whole stack in one sitting: migration -> model -> service/controller -> route -> Inertia page -> shared component -> tests. Never leave a half-built layer and switch tasks.
- **One feature at a time.** A feature is done only when it works for ALL three parties and its tests pass.
- **Follow the build order.** `docs/modules/23-implementation-plan.md` defines which module is built first, which modules are coupled and must be built together, and each wave's features. Work does not jump waves.
- **Start every task at the tracker.** Open `docs/modules/23-implementation-plan.md` §1.5 first; build exactly its NEXT UP item, and after finishing it update the tracker (tick the slice, promote the next one).
- **No loose ends:** no dead routes, orphaned pages, TODO/FIXME markers, commented-out code, unused imports/props, unguarded routes, or stale seed data. Before closing any task, verify all of these.
- **No scope creep.** A task covers the stated feature only; related improvements become their own task.

## 3. Test after every feature (not after the module)

- Write the feature test as part of the feature: happy path, negative/403 path, and state-transition/money exactness where relevant.
- Every new role-gated route MUST be added to the access-control suite (`tests/Feature/DzimbaAccessControlTest.php`).
- Before a feature/wave counts as done, run in order:
  1. `php artisan test --filter=<SuiteName>`
  2. `php artisan test` (full suite)
  3. If front-end changed: `npm run build` (clean, no unused-import warnings)
  4. If routes changed: `php artisan route:list` (no orphan/duplicate names)
  5. If DB changed: `php artisan migrate:fresh --seed` on the dev DB, then re-run the suite.

## 4. Backend coding standards (check constantly)

- Follow `docs/06-coding-standards.md`, `docs/03-architecture-and-data.md` and Laravel conventions.
- Thin controllers: validate -> call a service/controller action -> return. No business logic in controllers, models or views.
- Every protected group carries role middleware (`admin`, `role`). Owners/tenants must never see another user's data (row-level scoping; return 403/404).
- Money is `DECIMAL(12,2)`, computed server-side, never float. Status transitions are explicit state machines with history where required.
- No secrets, credentials or production data in code/tests. No commented-out code.
- Migration changes stay forward-compatible; never hand-edit lockfiles.

## 5. Frontend coding standards (check constantly)

- Follow the React/TypeScript rules in `docs/06-coding-standards.md` (purity, no render side effects, Hook rules, typed props, strict TS, no `any`).
- Reuse shared components (`Components/Shared/StatusBadge.jsx`, `StatCard.jsx`, `Components/ui/*`) and Tailwind tokens. Does not reinvent UI.
- No dead imports, no session/context data fetched client-side when Inertia props already provide it; server data is authoritative.
- New pages use the existing role-aware layouts (`MainLayout`, `AdminLayout`); nav additions must resolve to live routes.

## 6. Demo data & access (kept consistent)

- Demo users are always seeded and working: `admin@system.local` (Superuser), `owner@dzimba.local` (Owner), `tenant@dzimba.local` (Tenant), `staff@dzimba.local` (Admin) - password `password123`.
- Demo users keep `password_changed_at` set (no forced-change loop in demos/tests).
- New DB tables need seed rows so all three parties can demo the feature.

## 7. Documentation keep-alive

- Every module feature updates its doc in `docs/modules/`.
- Wave/build-plan changes update `docs/modules/23-implementation-plan.md` (rank, coupling, impacts, waves) and are reflected back into the party matrix in `docs/modules/00-overview.md`.
- **After every finished feature, update the §1.5 status tracker in `docs/modules/23-implementation-plan.md`** (tick the completed slice, promote the next-up feature). It must never go stale — this is how we always know what is next and what is in flight.

## 8. Before finishing ANY task (checklist)

- [ ] Feature works end to end in the browser for all relevant parties.
- [ ] Feature + negative + permission-matrix tests written and passing; full suite green.
- [ ] Build clean (no unused imports), route:list clean, seed data updated if needed.
- [ ] No TODO/FIXME/dead code/loose ends left behind.
- [ ] Docs + build plan updated if the feature touched them.

---

# Original product concept (background only, superseded by the rules above)

hello there is an  erp I wnat to develop, its for removing the boring agents , where property owners can upload their own prperties and tenants can just log and see if there are open without agent fees, so will make people pay though affordable subs

Yes — I understand the concept. You're essentially looking at a direct property marketplace / property rental ERP that removes the traditional middleman where possible.

The key idea would be:

Property owners list directly → tenants search directly → tenant contacts/applies to owner → no traditional agent commission.

And instead of charging huge agent fees, the platform makes money through affordable subscriptions and optional premium services.

🏠 The platform could have 3 main users

1. Property Owners / Landlords

Create an account
Add properties
Upload photos/videos
Add location
Set rent
Specify property type
Number of bedrooms/bathrooms
Amenities
Availability date
Deposit requirements
Lease terms
Mark property as Available / Reserved / Occupied
Receive tenant enquiries
Manage applications
Manage multiple properties from one dashboard

2. Tenants

Create an account
Search available properties
Filter by:
Location
Price
Property type
Bedrooms
Amenities
Furnished/unfurnished
Availability
View photos and property details
Save favourites
Contact the owner
Request viewing
Submit an application
Track applications

3. Platform Administrator
This is where your ERP aspect becomes powerful.

Verify property owners
Moderate listings
Verify properties
Manage subscriptions
Manage payments
Handle reported listings
Manage users
Manage locations
Analytics
Dispute/report management
Platform revenue
Subscription plans
💰 Your business model

I wouldn't make the platform completely free.

Instead, you could have something like:

Plan	Example
Free	1 property listing
Basic	5 listings
Professional	20 listings
Business	Unlimited/many listings

For example, rather than an owner paying an agent a large commission, they pay the platform a small monthly subscription.

You could also have optional paid services:

Featured property
Property promoted to top of search
Verified owner badge
Property verification
Professional photography
Tenant screening
Digital lease generation
Online rent collection
Property management
SMS notifications

That gives you multiple revenue streams rather than relying only on subscriptions.

🚀 But I would take your idea one step further

Don't build it as merely a property listing website.

Build it as a Property Management + Rental Marketplace ERP.

The marketplace gets people onto the platform.

The ERP keeps landlords using it.

For example:

Property Management

Owner sees:

My Properties

House A — Available
House B — Occupied
House C — Maintenance
House D — Available

Then for an occupied property:

Tenant → Lease → Rent → Payments → Maintenance → Documents

So eventually the owner can manage their entire rental business from your platform.

🔥 The really interesting part: remove the agent, not necessarily the services

This is where I think the business could become much stronger.

Instead of:

Landlord → Agent → Tenant

you create:

Landlord → Platform ← Tenant

But the platform can provide services traditionally handled by agents.

For example:

Property discovery
→ Platform

Property advertising
→ Platform

Tenant enquiries
→ Platform

Viewing requests
→ Platform

Applications
→ Platform

Lease/document management
→ Platform

Rent collection
→ Platform

Maintenance requests
→ Platform

So you're not just saying:

"We have properties without agents."

You're saying:

"Everything you need to rent and manage property, without the traditional agent commission."

That's a much stronger proposition.

🌍 And this could work particularly well in Zimbabwe

You could start with one city and expand.

For example:

Phase 1

Harare
Residential rentals
Houses
Flats
Townhouses
Rooms

Phase 2

Bulawayo
Mutare
Gweru
other cities

Phase 3

Commercial properties
Offices
Shops
Warehouses
Land

Eventually it becomes a broader property marketplace + property management platform.

🧠 Important design decision

I'd separate the system into two products conceptually:

Public Marketplace

www.yourplatform.com

Where tenants see:

🏠 3 Bedroom House
Borrowdale
$850/month
View Property

and search/filter properties.

Owner ERP

app.yourplatform.com

Where landlords see:

Dashboard

Properties: 24
Available: 6
Occupied: 18
Applications: 12
Pending enquiries: 8
Rent due: $4,250
This month's income: $12,800

That makes it feel like a serious SaaS product rather than just another property website.

And there's another potentially powerful feature

Property verification.

One of the biggest problems with direct marketplaces is trust.

Someone could upload:

"Beautiful 4-bedroom house — $300/month"

and it might not even exist.

So you can introduce:

Verified Property ✓

and potentially:

Verified Owner ✓

This becomes a major competitive advantage.

The platform could have an internal verification workflow where administrators verify ownership/listing legitimacy before giving the property a verified badge.

If you want, I can help you design the entire ERP from the ground up — including the modules, user roles, subscription model, complete workflow, database tables, dashboards, property/tenant lifecycle, payments, verification system, and the Laravel/Vue architecture so you have an actual development blueprint rather than just the idea.

now show the modules you are going to develop 

Absolutely. I’d structure this as a Property Rental Marketplace + Property Management ERP, rather than just a property-listing website.

🏢 Proposed ERP Modules
1. 👤 User & Account Management

For all users on the platform.

Tenant accounts
Property owner/landlord accounts
Staff/admin accounts
User profiles
Role & permission management
Account verification
Login/security management
Account status management
2. 🏠 Property Management

The core landlord module.

Add properties
Edit/update properties
Property categories
House
Flat/Apartment
Townhouse
Cottage
Room
Commercial
Land
Property details
Bedrooms/bathrooms
Amenities
Property features
Photos/videos
Property location
Rental price
Deposit
Availability status
Property documents
Property verification
Property history

Property statuses:
Draft → Pending Verification → Published → Reserved → Occupied → Unavailable

3. 🔎 Property Marketplace / Search

The tenant-facing side.

Property search
Advanced filters
Location search
Price range
Property type
Bedrooms
Bathrooms
Amenities
Furnished/unfurnished
Availability
Sort by price/date/relevance
Property details page
Photo gallery
Map/location
Owner information
Verified property badge
4. ❤️ Favourites & Saved Searches

Tenants can:

Save properties
Remove favourites
Save searches
Receive notifications when matching properties become available
Compare shortlisted properties
5. 📩 Enquiries & Communication

Instead of the agent being the middleman:

Tenant → Platform → Property Owner

Features:

Property enquiries
Owner/tenant messaging
Viewing requests
Enquiry tracking
Notifications
Email/SMS/WhatsApp integration
Communication history
6. 📅 Property Viewing Management

A proper viewing workflow.

Tenant:

Request Viewing

Owner:

Accept / Reject / Reschedule

System:

Confirmed Viewing

Features:

Viewing requests
Available viewing slots
Scheduling
Rescheduling
Viewing reminders
Viewing history
Viewing status
7. 📝 Rental Applications

Once a tenant likes a property:

Apply for Property

The owner can then:

Review applications
View applicant information
Accept
Reject
Request additional information
Shortlist applicants

Tenant sees:

Application Submitted
Under Review
Approved
Rejected

8. 📄 Lease & Agreement Management

Once an application is approved:

Create rental agreement
Lease templates
Tenant/landlord details
Property details
Rent
Deposit
Lease start/end dates
Payment terms
Digital documents
Lease renewal
Lease termination
Agreement history
9. 💰 Rent & Payment Management

This is where the platform starts becoming a real ERP.

For landlords:

Rent schedules
Rent invoices
Payment tracking
Outstanding rent
Payment history
Deposits
Late payments
Receipts
Tenant balances
Monthly rental income

For tenants:

Amount due
Payment history
Receipts
Upcoming payments
10. 🔧 Maintenance Management

Tenant reports:

"The kitchen tap is leaking."

System creates:

Maintenance Request #MR-001

Owner can:

View request
Assign technician
Approve cost
Track progress
Close request

Statuses:

Reported → Assigned → In Progress → Completed → Closed

11. 👨‍🔧 Service Provider / Contractor Management

For plumbers, electricians, builders, etc.

Contractor profiles
Services
Contact details
Assigned jobs
Job history
Costs
Ratings
Performance

This could eventually become another revenue stream.

12. 💳 Subscription Management

This is critical to your business model.

Owners subscribe to use the platform.

Example:

Free

1 property
Basic listing

Basic

5 properties
Enhanced listing

Professional

20 properties
Analytics
Featured listings

Business

Higher/unlimited property capacity
Advanced property management
Priority support

The system manages:

Plans
Subscriptions
Billing
Payments
Expiry
Renewals
Upgrades/downgrades
Subscription history
13. ⭐ Featured & Advertising Management

Owners can pay a small additional amount to promote properties.

Examples:

Featured Property

Top Listing

Premium Property

Homepage Promotion

The owner pays → property receives increased visibility.

14. ✅ Property & Owner Verification

One of the most important modules.

We need to build trust into the marketplace.

Admin can verify:

Owner
Property
Listing
Supporting documents
Contact information

Then display:

✓ Verified Owner

✓ Verified Property

This helps distinguish your platform from random social-media property listings.

15. 🔔 Notification Management

Central notification system.

Notifications for:

New enquiry
New application
Viewing request
Viewing confirmation
Application approval
Rent due
Payment received
Lease expiring
Maintenance request
Subscription expiry
New matching property

Channels:

In-app
Email
SMS
WhatsApp where appropriate
16. 📊 Landlord Dashboard

A landlord should immediately see:

Properties: 18
Available: 4
Occupied: 14
Applications: 7
Pending Enquiries: 12
Rent Due: $3,250
Monthly Income: $9,800

Plus:

Property performance
Occupancy
Rental income
Applications
Enquiries
Maintenance
Upcoming lease expiries
17. 📈 Reports & Analytics

For landlords:

Rental income
Occupancy rate
Vacant properties
Property performance
Applications
Tenant statistics
Maintenance expenditure
Monthly income
Outstanding rent

For the platform:

Registered owners
Registered tenants
Properties
Active listings
Occupancy
Subscription revenue
Featured-listing revenue
New registrations
Most searched locations
Most popular property types
18. 🛡️ Admin / System Management

The platform administration centre.

User management
Property management
Owner verification
Property verification
Subscription management
Payment management
Reports
Complaints
Reported listings
Content moderation
Locations
Property categories
Amenities
System configuration
Audit logs
19. 🚨 Complaints, Reports & Disputes

Because we're removing agents, trust and accountability become extremely important.

Users can report:

Fake property
Fake owner
Incorrect information
Scam attempt
Misleading price
Inappropriate listing
User misconduct

Admin investigates and records the resolution.

20. 📚 Document Management

Central document repository for:

Lease agreements
Property documents
Receipts
Payment documents
Verification documents
Maintenance documents
Other supporting documents
🔥 The overall architecture

I'd divide the system into four major areas:

                 PROPERTY PLATFORM
                       │
        ┌──────────────┼──────────────┐
        │              │              │
     TENANTS        OWNERS          ADMIN
        │              │              │
        ▼              ▼              ▼
    Marketplace    Owner ERP      Administration
        │              │              │
        └──────────────┼──────────────┘
                       │
                 CORE SERVICES
                       │
       ┌───────────────┼────────────────┐
       │               │                │
    Payments       Notifications     Documents
       │               │                │
       └───────────────┼────────────────┘
                       │
                  REPORTING
The most important distinction

The tenant doesn't pay an agent.

The landlord doesn't pay an agent commission.

Instead:

Landlord → Affordable platform subscription

and optionally:

Landlord → Featured listing / premium services

That gives you a potentially scalable SaaS model.

I would build the MVP around 10 core modules first—User Management, Property Management, Marketplace, Search, Enquiries, Viewings, Applications, Verification, Subscriptions, and Admin—then add leases, rent collection, maintenance, contractors and advanced ERP features in Phase 2.