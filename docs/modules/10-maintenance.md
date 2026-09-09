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