# Module 17 - Reports & Analytics

> Phase: Phase 2 (MVP: minimal aggregates) | Primary actors: Owner (own data), Admin (platform-wide)

## 1. Purpose

Turns collected data into decisions. Owners get portfolio performance; admin gets platform health, monetisation and market intelligence; tenants get lightweight personal insights.

## 2. Roles & Responsibilities

| Actor | Actions |
|---|---|
| **Admin** | Platform KPIs: registered owners/tenants, properties, active listings, occupancy, subscription revenue (MRR/ARR), featured ad revenue, new registrations, top searched locations, popular property types, conversion funnel (search → enquiry → application → lease). |
| **Owner** | Rental income, occupancy rate, vacant properties, property performance, applications by property, maintenance expenditure, outstanding rent, enquiry-to-lease conversion. |
| **Tenant** | Light: applications sent, favourites, viewings attended (private to tenant). |

## 3. Functional Requirements

- FR-01 Time-range selection (7/30/90/365 days, custom).
- FR-02 Export filters to CSV (and PDF Phase 3).
- FR-03 Scheduled digest: monthly income report (owner), monthly platform report (admin).
- FR-04 All figures derive from ledgers (payments/invoices) rather than stored counters.
- FR-05 Drill-down from a KPI to the underlying rows.

## 4. Non-Functional Requirements

- NFR-01 Heavy aggregations cached; materialised monthly tables for platform reports.
- NFR-02 Export file generation is queued.
- NFR-03 Display currency consistent with platform setting.

## 5. Workflows & Pseudo Sentences

1. **Owner report** - When an owner opens Reports, the system aggregates income, occupancy and vacancy for the selected range; when the owner exports, the system queues a CSV and emails the link.
2. **Platform report** - When admin opens Analytics, the system computes registration growth, listings, occupancy and MRR; when admin drills down on MRR, the system lists subscription invoices for the period.
3. **Digest** - When the monthly cron fires, the system builds each owner's income report; then the system emails the summaries; for admin the system compiles a platform digest.
4. **Conversion funnel** - When analysing the funnel, the system counts searches → enquiries → applications → approvals → signed leases per month; when a stage drops sharply, the system flags it on the admin dashboard.
5. **Market signals** - When registering searches, the system counts location and type combinations; when anomalies appear (a city spikes), the system surfaces them to admin for supply planning.

## 6. Data Model

Read-only over ledgers: `properties`, `rental_applications`, `enquiries`, `viewing_requests`, `rent_invoices`, `payments`, `subscription_invoices`, `ad_placements`, `property_favourites`, `auth_login_logs`.

Optional materialised tables: `daily_platform_stats`, `monthly_owner_income`.

## 7. Integrations & Dependencies

- Modules 02, 05, 06, 07, 09, 12, 13, 01 (registrations/logins). Feeds 16 (dashboard) and 18 (admin console).

## 8. Acceptance Criteria

AC-01 Report totals reconcile with invoice/payment ledgers.
AC-02 Owner reports are isolated to their portfolio.
AC-03 Exports contain exactly the filtered dataset.
AC-04 Admin funnel figures match the event tables.