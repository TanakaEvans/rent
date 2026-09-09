# Module 07 - Rental Applications

> Phase: MVP | **Implemented (Wave 3 slice 1)** | Primary actors: Tenant (applicant), Owner (reviewer), Admin (oversight)

> **Implementation status (Wave 3 slice 1):** Apply + review + decision flow built across the stack. Tenant applies to any `available` property from the public detail page (`tenant.applications.store`, message ≤1000, one **active** application per tenant per property; rejected is not active, so re-application is allowed). Owner reviews a grouped per-property screen (`owner.applications.index`) with inline **shortlist** (toggle pending↔shortlisted), **approve**, and **reject** (reason required, stored in `reject_reason`, shown to the tenant). State machine: pending↔shortlisted; pending/shortlisted → approved/rejected; approved/rejected are terminal. Approval records `reviewed_by` and enforces **exclusivity** (one approved per property) but — by design for this slice — does **NOT** move the property or auto-reject the other applicants: that handover lands in Wave 3 slice 2 (Lease create) per FR-05/AC-01/AC-04. Notifications: `NewApplicationNotification` → owner on apply, `ApplicationApprovedNotification`/`ApplicationRejectedNotification` → tenant on decision. Tenant dashboard + `Tenant/Applications` show live status with prose. Confirmed in `CommitTest` (16 tests) + access matrix.

## 1. Purpose

The formal application layer. A tenant applies for a property; the owner reviews, shortlists, approves or rejects. Approval triggers the property status change to `reserved` and hands over to lease creation (Module 08).

## 2. Roles & Responsibilities

| Actor | Actions |
|---|---|
| **Tenant** | Submit an application with an optional message, track status (pending/shortlisted/approved/rejected), withdraw if needed. |
| **Owner** | Review applicants, shortlist, request more info, approve/reject, one approved application per property at a time. |
| **Admin** | Review disputes, monitor for duplicate/fraudulent applications, configure application form fields. |

## 3. Functional Requirements

- FR-01 Submit application against `(property_id, applicant_id)` with optional message (≤1000).
- FR-02 Validations: property must be `available`; an applicant cannot double-apply to the same property.
- FR-03 Statuses: `pending`, `shortlisted`, `approved`, `rejected`.
- FR-04 Owner sees all applications for their properties only.
- FR-05 On approval: property → `reserved`; other pending applications auto-rejected.
- FR-06 Tenant dashboard shows each application with current status and property thumbnail.
- FR-07 Phase 2: structured application form (employment, references, move-in date, guarantor).

## 4. Non-Functional Requirements

- NFR-01 Unique active application per `(property_id, applicant_id)`.
- NFR-02 Indexed `(property_id, status)` and `(applicant_id, status)` for listings.
- NFR-03 Approval is exclusive - exactly one approved application per available property.

## 5. Workflows & Pseudo Sentences

1. **Apply** - When a tenant clicks Apply on an available property, the system validates eligibility; when valid, the system creates a `rental_applications` row with status `pending`; then the system notifies the owner; finally the tenant dashboard shows the application under "Applied".
2. **Review** - When the owner opens applications, the system groups them by property; when the owner shortlists a tenant, the system sets status `shortlisted`; when more info is needed, the system requests details via the messaging thread.
3. **Approve** - When the owner approves an applicant, the system sets the application to `approved`; then the system sets the property status to `reserved`; after that the system auto-rejects the remaining pending/shortlisted applications; finally the system notifies the successful tenant to proceed with the lease.
4. **Reject** - When the owner rejects an application, the system records the reason (required for stats); then the system notifies the tenant with a polite standard message.
5. **Occupied** - When the approved tenant signs the lease and moves in, the owner sets the property status to `occupied`.

## 6. Data Model

| Table | Key Columns |
|---|---|
| `rental_applications` | id, property_id FK, applicant_id FK (auth_users), message (≤1000), status (pending/shortlisted/approved/rejected), **reject_reason (added Wave 3 slice 1)**, **reviewed_by FK (added Wave 3 slice 1)**, timestamps |

Relationships: `properties` hasMany `rental_applications`; `auth_users` (tenant) hasMany `rental_applications`.

### Planned Phase-2 Columns

```text
rental_applications += employment (json), references (json), guarantor (json),
                       move_in_date (date), applied_price (decimal), reviewed_by (FK)
```

## 7. Integrations & Dependencies

- Module 02 (property status), Module 05 (enquiries/messages), Module 08 (lease onboarding), Module 16 (pending applications card), Module 17 (approval rates).

## 8. Acceptance Criteria

AC-01 Shared property auto-rejects other applicants on approval. — **Wave 3 slice 2** (with lease handover).
AC-02 A tenant cannot submit two active applications for the same property. — ✅ implemented.
AC-03 The owner sees only their properties' applications. — ✅ implemented.
AC-04 Approval flips property status to `reserved`. — **Wave 3 slice 2** (approval is deliberately inert in slice 1).

## 9. Implementation Status

- **Wave 3 slice 1 — DONE**: FR-01, FR-02 (one active per tenant/property; re-apply after rejection), FR-03, FR-04 (owner isolation 404 on foreign rows), FR-06 (status + prose on both dashboards), NFR-01, NFR-02, NFR-03 (sole-approved-per-property). FR-05 (auto-reject + reserved) and AC-01/AC-04 land with the lease slice (M8 slice 2). FR-07 structured form is Phase 2.
- Tests: `tests/Feature/CommitTest.php` (16) + access matrix; notifications asserted with `Notification::fake`.