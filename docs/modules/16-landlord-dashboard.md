# Module 16 - Landlord (Owner) Dashboard

> Phase: MVP | Implemented (core cards) | Primary actors: Owner

## 1. Purpose

The owner's command centre. One glance answers: how many properties, what is available/occupied, outstanding applications/enquiries, rent owed and monthly income. It is the "ERP feel" that retains landlords.

## 2. Roles & Responsibilities

| Actor | Actions |
|---|---|
| **Owner** | See snapshot KPIs, list/manage own properties, monitor applications/enquiries, see rent-due banner, navigate to modules. |
| **Admin** | none directly (feeds via platform data). |
| **Tenant** | none (not exposed). |

## 3. Functional Requirements

- FR-01 KPI cards: Properties, Available, Occupied, Applications, Pending Enquiries, Rent Due, Monthly Income.
- FR-02 Properties table: thumbnail, title, type, price, status badge, quick actions (view/edit/mark reserved/occupied).
- FR-03 Alert banner for urgent items (rent due soon, pending applications beyond SLA, maintenance emergencies).
- FR-04 Quick links to Property list, Applications, Viewings, Enquiries, Subscriptions.
- FR-05 StatusBadge component reuses property statuses: available/reserved/occupied/unavailable.
- FR-06 Phase 2: occupancy chart, income trend, upcoming lease expiries.

## 4. Non-Functional Requirements

- NFR-01 Dashboard queries scoped to `owner_id` (row-level).
- NFR-02 Aggregations cached for 60s at high volume.
- NFR-03 Page renders server-side via Inertia with props; no client-side API for KPIs.

## 5. Workflows & Pseudo Sentences

1. **Load** - When the owner opens `/owner`, the system aggregates their properties, applications and enqueues rent data; then the system renders KPI cards and the properties table.
2. **Status change** - When the owner marks a property reserved/occupied, the system updates the status; then the dashboard badge and counts refresh immediately.
3. **Rent due banner** - When outstanding rent exists for an occupied property, the system shows the amount on the rent-due banner; when the tenant pays, the banner clears.
4. **Deep-link** - When the owner clicks an application count, the system navigates to the filtered applications list.
5. **Period view (Phase 2)** - When the owner selects a month, the system recomputes income and occupancy for that window.

## 6. Data Model

Read-only aggregation over: `properties`, `rental_applications`, `property_favourites`, `enquiries`, `rent_invoices`, `payments` - all scoped by `auth_users.id = owner`.

## 7. Integrations & Dependencies

- Modules 02, 07, 05, 09, 15 and 17 supply data; `StatusBadge` shared component.

## 8. Acceptance Criteria

AC-01 Counts match the underlying tables exactly.
AC-02 Owner never sees another owner's data.
AC-03 Status changes reflect in badges/keys without a manual refresh.