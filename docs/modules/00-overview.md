# Dzimba - Module Documentation

> Property Rental Marketplace + Property Management ERP
> Generated: 2026-09-09 | App: Laravel + Inertia + React | DB: MySQL

This folder contains the detailed requirements and design documentation for the **Dzimba** platform, written per module and organized around the three parties that use the system:

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

Dzimba is laid out in four areas:

```
                  DZIMBA PLATFORM
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
| 10 Maintenance | Escalation | Assign/approve | Report |
| 11 Contractors | Registry + ratings | Hire | - |
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
- Roles: `Superuser`, `Admin`, `Owner`, `Tenant`, `Staff`.
- Routes/UI: public marketplace `/` (search + filters + map + saved searches + lifecycle + trust/reports + owner/admin analytics — the Property Marketplace batch B1-B6 is DONE, full suite 295 green), login, `/dashboard`, `/admin/dashboard`, `/owner`, `/tenant`.