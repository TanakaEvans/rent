# Module 10 - Maintenance Management

> Phase: Phase 2 | Primary actors: Tenant (reporter), Owner (approver), Admin (escalation)

## 1. Purpose

Handles repair and upkeep requests for occupied properties. Tenants report issues; owners triage, approve cost, assign a contractor and track resolution. A documented history protects all parties at lease end.

## 2. Roles & Responsibilities

| Actor | Actions |
|---|---|
| **Tenant** | Report a fault with category, description, photos; track status; confirm completion. |
| **Owner** | Review requests, approve/decline with reason, approve cost estimate, assign contractor, mark completed/closed. |
| **Admin** | Escalation for disputed or long-unresolved requests, contractor misconduct reports. |

## 3. Functional Requirements

- FR-01 Report a maintenance request against `(property_id, tenant_id)`.
- FR-02 Categories: plumbing, electrical, appliance, structural, pest, safety, other.
- FR-03 Statuses: `reported`, `assigned`, `in_progress`, `completed`, `closed`, `declined`.
- FR-04 Priority: `low`, `medium`, `high`, `emergency` (emergency routes to immediate owner + admin notification).
- FR-05 Owner assigns a contractor (Module 11) and approves quoted cost.
- FR-06 Tenant confirms completion; owner closes after inspection.
- FR-07 Urgency SLA timers: high/emergency breaches escalate to admin.

## 4. Non-Functional Requirements

- NFR-01 SLA breach scheduling (e.g. emergency = 24h first response).
- NFR-02 Photo uploads limited by size/type.
- NFR-03 Cost history feeds landlord maintenance-expenditure reports.

## 5. Workflows & Pseudo Sentences

1. **Report** - When a tenant reports "kitchen tap leaking", the system creates request `MR-####` with priority; when priority is emergency, the system additionally pages the owner and admin; the system sends the owner an in-app notification.
2. **Assign** - When the owner approves the request, the system asks for the quoted cost; when the owner assigns a contractor, the system sets status `assigned`; then the system notifies the contractor with the job brief.
3. **Track** - When the contractor starts work, the system moves status to `in_progress`; when work ends, the contractor marks complete; when the tenant confirms, the owner marks `completed` then, after inspection, `closed`.
4. **Decline** - When the owner declines a request, the system records the reason; when the tenant disagrees, the system opens a dispute (Module 19) with the request attached.
5. **Escalate** - When an SLA is breached, the system escalates to admin; when the admin intervenes, the system logs the intervention; the case then continues from the new assignee.

## 6. Data Model

| Table | Key Columns |
|---|---|
| `maintenance_requests` | id, property_id FK, tenant_id FK, category, priority, title, description, status, approved_quote (decimal), assigned_contractor_id FK, resolved_at, timestamps |
| `maintenance_actions` | id, request_id FK, actor_id FK, action, notes, created_at |

Relationships: `maintenance_requests` belongsTo property/tenant/contractor; hasMany actions (timeline).

## 7. Integrations & Dependencies

- Module 02 (property), Module 11 (contractors), Module 15 (notifications + SLA), Module 17 (maintenance spend), Module 19 (disputes).

## 8. Acceptance Criteria

AC-01 Request numbering is unique and trackable.
AC-02 Contractor receives assignment notifications.
AC-03 Emergency requests cannot be missed by the owner inbox.
AC-04 Completed-and-closed requests feed maintenance reports.

## 9. Implementation Status

> Wave 5 slices 1-3 (report & triage, assignment M11, **track & close out**) DONE (2026-09-10) — every rule below is configuration-driven through `ConfigurationService` (see `24-system-configuration.md`), never a code constant.

### Built (slice 1 - report & triage)

- **Tables** (`2026_09_09_000028`): `maintenance_requests` (request_no ≤20 unique `MR-YYYY-NNNNN`, property_id/tenant_id FKs cascade, category, priority, title, description, status default `reported`, sla_due_at, escalated_at, index (status, sla_due_at)); **`approved_quote` DECIMAL(12,2) + `assigned_contractor_id` FK landed with slice 2; `tenant_confirmed_at` + `resolved_at` landed with slice 3**; `maintenance_actions` immutable timeline (request_id FK cascade, actor_id nullable FK auth_users, action, notes ≤500, created_at only).
- **Models**: `MaintenanceRequest` (STATUSES/TRANSITIONS/TERMINAL/PRIORITIES, `canTransitionTo`, relations property/tenant/actions/**contractor**) + `MaintenanceAction` (UPDATED_AT=null, request()/actor(), FK pinned `request_id` on `MaintenanceRequest::actions()`).
- **Service** `MaintenanceService` (final, ConfigurationService-injected): `create` (actively-leased properties only — foreign lease → 404; category/priority from config; `sla_due_at` = now + priority hours; unique MR number from max(id)+1; `reported` timeline action; owner notified `NewMaintenanceRequestNotification`; **emergency additionally pages every Admin/Superuser** via `EmergencyMaintenanceNotification` — AC-03); `listForTenant`, `listForOwner` (row-scoped); `escalateDue` (idempotent once-per-request sweep: reported + past SLA + `escalated_at` guard → `escalated` action + `MaintenanceSlaBreachedNotification` to staff; master-switch `maintenance.escalation_enabled`); `listEscalationsForAdmin`, `acknowledge` (reported+escalated-only → clears `escalated_at`, logs ownership).
- **Sweep**: `maintenance:escalate` command scheduled daily 06:00 (`routes/console.php`) — NFR-01.
- **UI**: `Tenant/Maintenance` (report form on leased homes + request tracker with SLA countdown + emergency banner); `Owner/Maintenance/Index` (repair queue sorted breach-then-priority, SLA chips, escalated badges); `Admin/Maintenance/Escalations` (staff queue + "Take ownership"); Wrench nav entries (owner + tenant MainLayout, AdminLayout); StatusBadge `reported`/`assigned`/`in_progress` tones.
- **Demo data**: `MaintenanceSeeder` (after PropertySeeder, silent direct rows) — MR-2026-00001 reported plumbing (medium, +96h SLA), MR-2026-00002 emergency safety request escalated past SLA (appears in the admin queue).
- **Tests**: new `OperateTest` suite (report + MR format/SLA, emergency pages owner+staff, active-lease-only 404, category/priority validation, config-driven SLA hours, escalate-once idempotency + staff paged, non-reported skipped, master switch, admin queue + ack, ack guard, owner isolation, tenant leased-props page, seeded emergency badged, state-machine spec); access matrix rows.

### Built (slice 2 - assignment M11)

- **Tables** (`2026_09_09_000029`): `contractors` (user_id nullable FK auth_users nullOnDelete, business_name ≤120, contact ≤120, service_area JSON, status ENUM(unverified,vetting,verified,suspended) default unverified, rating_avg DECIMAL(3,2) default 0, jobs_completed unsignedInt default 0, verified_at nullable, index (user_id,status)) + `contractor_trades` (contractor_id FK cascade, trade ≤60, rate DECIMAL(10,2) nullable). `maintenance_requests` + `approved_quote` DECIMAL(12,2) nullable + `assigned_contractor_id` FK contractors nullOnDelete (index).
- **Registry** `ContractorService`: `register` always lands in `vetting` (admin confirms before `verified`), `setStatus` follows the state machine (`unverified → vetting → verified ↦ suspended`), `verifiedForAssign` (only verified, best-rating/jobs first — the owner dropdown), `listForAdmin` (paginated + jobs count). `ContractorController` admin registry (`admin.contractors.{index,store,status}`) — every status change audited via `setStatus`.
- **Assignment** `MaintenanceService::assignToContractor`: 404 unless the request's property belongs to the caller; **status must be `reported` (double-assign blocked)**; quote `required|numeric|min:0` stored server-side `number_format(2)` DECIMAL(12,2); **only `verified` contractor assignable** (AC-01 + AC-03 — vetting/suspended rejected with a field error); sets `assigned` + quote + contractor, clears `escalated_at`, logs an immutable `assigned` timeline action (`quote — business` notes, actor = owner), notifies the contractor user `MaintenanceAssignedNotification` (job brief + quote — Module 10 AC-02).
- **UI**: `Contractor/Maintenance/Index` job desk (assigned briefs + quote chip); Owner queue gains an `AssignForm` on `reported` cards (verified-contractor select + quote, `owner.maintenance.assign`) and shows a contractor/quote chip on `assigned` cards; "Awaiting assignment" stat card; `Admin/Contractors/Index.jsx` registry (register form w/ trade rows + status transitions); MainLayout contractor "My Jobs" section + AdminLayout "Contractor Registry"; StatusBadge `unverified`/`vetting`/`verified` tones.
- **Demo data**: `ContractorSeeder` (Bulawayo Plumbing Co. verified + linked to demo `contractor@dzimba.local`, Mura Building Services verified standalone, CleanFlow Pest Solutions vetting) runs **before** `MaintenanceSeeder`, which now seeds MR-2026-00001 as **assigned** @ $85.00 + `assigned` action; MR-2026-00002 stays reported + escalated (admin queue + assignable).
- **Tests**: OperateTest extends to 24 (register→vetting→verify: quote normalisation + owner timeline + escalation cleared + contractor notified; only-verified; double-assign; cross-owner 404; quote required/negative; contractor jobs own-briefs-only + profile-empty stranger; registry state machine; suspended → no new assignments + verified_at cleared); access matrix +9 rows. Full suite 378 passed / 1806 assertions.

### Built (slice 3 - track & close out, M10/M11)

- **Columns** (`2026_09_09_000030`): `maintenance_requests.tenant_confirmed_at` + `resolved_at` nullable timestamps.
- **Lifecycle** `MaintenanceService` (all row-scoped, state-machine-gated, timeline-recorded, notified): `start` (assigned→in_progress — only the **assigned contractor** via their linked profile, 404 otherwise; `started` action; owner notified), `complete` (in_progress→completed with a required summary ≤500; pages **owner + tenant** `MaintenanceWorkCompletedNotification`), `confirm` (tenant-only via request ownership, requires `completed`, **one-shot** — second confirm rejected; stamps `tenant_confirmed_at`; owner notified), `close` (owner-only via property ownership, requires `completed` **and** the tenant's confirmation — the FR-06 gate; sets `closed` + `resolved_at`, retains `approved_quote`, so completed-and-closed requests feed maintenance spend, AC-04/NFR-03; optional inspection note; tenant + contractor notified `MaintenanceClosedNotification`).
- **Routes**: `owner.maintenance.close`, `tenant.maintenance.confirm`, `contractor.maintenance.{start,complete}` (whereNumber + descriptions).
- **UI**: contractor desk gains Start + complete-with-note + "Fix confirmed" chip; tenant page gains Confirm-fix (completed cards) + confirmed chip; owner queue includes `completed` cards with the tenant-confirmed chip + "Inspect & close" form.
- **Demo data**: unchanged — the seeded MR-2026-00001 (assigned, $85.00) lets all three parties drive the lifecycle in the browser.
- **Tests**: OperateTest extends to 31 (start→complete full path with notes + owner/tenant notified; stranger-contractor 404; lifecycle gate matrix; one-shot confirm → owner close → `resolved_at` + quote retained + tenant/contractor notified; confirm-gates-close; own-request 404 + confirm-uncompleted rejected; cross-owner close 404); access matrix +9 rows (start/complete/confirm/close permissions + guest redirects). Full suite 394 passed / 1884 assertions.

### Next (slice 4 - contractor ratings, M11)

After a request closes the owner rates the contractor (1-5 + optional note); the system recomputes `rating_avg` and increments `jobs_completed` — ratings from completed jobs only, no self-rating (Module 11 NFR-01). Wave-tests: rating-only-after-completed; average correctness.