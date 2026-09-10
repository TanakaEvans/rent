# Module 11 - Service Provider / Contractor Management

> Phase: Phase 2 | Primary actors: Owner (hirer), Contractor (worker), Admin (registry)
> **IMPLEMENTED (Wave 5 complete, 2026-09-10)** — see §9. FR-01/FR-02/FR-03 landed with the registry + assignment + job life-cycle; FR-04 (ratings) completed by slice 4. FR-05/FR-06 are Phase 2/3.

## 1. Purpose

A registry of verified tradespeople (plumbers, electricians, builders, etc.) that owners assign maintenance jobs to. Becomes a marketplace of its own and a revenue stream through contractor subscriptions or job fees.

## 2. Roles & Responsibilities

| Actor | Actions |
|---|---|
| **Admin** | Register/verify contractors, manage trades/services, mediate ratings disputes, run contractor blacklists. |
| **Owner** | Browse verified contractors, assign jobs, approve quotes, rate completed work. |
| **Contractor** | Maintain profile and trades, receive job briefs, accept jobs, submit quotes, mark work done, respond to feedback. |

## 3. Functional Requirements

- FR-01 Contractor profile with trades/services, service areas, contact, documents (licence, insurance), photos. — ✅ **DONE (slice 2)**: profile (business_name, contact, service_area JSON) + `contractor_trades` (trade + rate); licensing documents stay Phase 2 (Module 20).
- FR-02 Verification status: `unverified`, `vetting`, `verified`, `suspended`. — ✅ **DONE (slice 2)** with the explicit state machine below + `verified_at` stamp.
- FR-03 Contractors assigned to maintenance jobs (Module 10) with cost and timeframe. — ✅ **DONE (slice 2)**: `assigned` status + `approved_quote` + job-brief notification. Timeframe is Phase 2.
- FR-04 Job history and ratings per contractor. — ✅ **DONE (slice 4)**: owner rates each closed job 1-5 + note (`contractor_ratings`, one per request); `rating_avg`/`jobs_completed` recomputed server-side. Job *history* listing stays Phase 2.
- FR-05 Owner can shortlist favourite contractors. — Phase 2.
- FR-06 Phase 3: contractor directory publicly searchable; contractors subscribe to receive jobs.

## 4. Non-Functional Requirements

- NFR-01 Ratings are computed from completed jobs only (no self-rating). — ✅ **DONE (slice 4)**: rated only once a request has fully `closed` (only closed jobs feed the aggregate), and only the property owner may rate.
- NFR-02 A contractor cannot be assigned the same job twice simultaneously. — ✅ **DONE (slice 2)**: `assignToContractor` requires status `reported`; a second assign on an `assigned` request is rejected.
- NFR-03 Licensing documents stored in Module 20 and required for `verified` status. — Phase 2 (documents module); registration always lands in `vetting` so an admin confirms before `verified`.

## 5. Workflows & Pseudo Sentences

1. **Register** - When a contractor applies, the system stores the profile with status `vetting`; when admin verifies documents, the system sets status `verified`; then the system makes them visible to owners in their service area. — ✅ **DONE (slice 2)** (`ContractorService::register` + `setStatus`; verification is always an admin decision).
2. **Assign** - When an owner assigns a job, the system creates the job with agreed quote and window; when the contractor accepts, the system locks the assignment and notifies the tenant. — ✅ **DONE (slice 2)** (owner picks verified contractor + approves quote → status `assigned`, contractor notified). Contractor explicit accept + a scheduling window are Phase 2.
3. **Complete & rate** - When the contractor marks the job complete, the owner inspects and closes; when the owner rates the contractor, the system recomputes the average and confidence score. — ✅ **DONE (slice 3 + slice 4)**: work complete (contractor), tenant confirm, and owner close landed in slice 3; the owner's 1-5 rating (with optional note) lands in slice 4 and recomputes `rating_avg`/`jobs_completed` server-side.
4. **Suspend** - When admin suspends a contractor, the system blocks new assignments; when the open jobs finish, the system marks the profile `suspended`; an owner can appeal on the contractor's behalf. — ✅ **DONE (slice 2)** partial: suspension immediately blocks new assignments (AC-03 tests) and clears the `verified` stamp; a "no open jobs" drain gate stays Phase 2.

## 6. Data Model

| Table | Key Columns |
|---|---|
| `contractors` | id, user_id FK auth_users (nullable), business_name ≤120, contact ≤120, service_area (json), status (unverified/vetting/verified/suspended) default unverified, rating_avg DECIMAL(3,2) default 0, jobs_completed unsignedInt default 0, **verified_at nullable**, timestamps — implemented `2026_09_09_000029` (index user_id,status) |
| `contractor_trades` | id, contractor_id FK cascade, trade ≤60, rate DECIMAL(10,2) nullable, timestamps — implemented `2026_09_09_000029` |
| `contractor_ratings` | id, request_id FK→maintenance_requests **unique** (one rating per job), contractor_id FK cascade, owner_id FK→auth_users nullable nullOnDelete, rating unsignedTinyInteger 1-5, note ≤500 nullable, timestamps, index contractor_id — implemented `2026_09_09_000031` |
| `maintenance_requests` | Module 10 — + `approved_quote` DECIMAL(12,2) + `assigned_contractor_id` FK nullOnDelete (slice 2) |

Relationships: `contractors` belongsTo user (optional); hasMany trades and jobs (via `maintenance_requests.assigned_contractor_id`).

**State machine (FR-02):** `unverified → vetting → verified ⇄ suspended`; `verified→suspended→verified` allowed, any other transition rejected (`Contractor::TRANSITIONS` + `ContractorService::setStatus`). Leaving `verified` clears `verified_at`.

## 7. Integrations & Dependencies

- Module 10 (jobs — assignment + approved quote), Module 15 (job notifications: `MaintenanceAssignedNotification` job brief), Module 17 (contractor performance, slice 3), Module 20 (documents, Phase 2), Module 19 (rating disputes, Phase 2).

## 8. Acceptance Criteria

- AC-01 Only verified contractors are assignable to jobs. — ✅ **DONE (slice 2)** (vetting/suspended rejected with `contractor_id` error).
- AC-02 Ratings reflect completed jobs only. — Wave 5 slice 4.
- AC-03 A suspended contractor receives no new assignments. — ✅ **DONE (slice 2)** (service guard + dropdown only lists verified).

## 9. Implementation Status (slices 2-3, 2026-09-10)

- **Backend**: `ContractorService` (`register` → always `vetting`; `setStatus` state-machine + `verified_at`; `verifiedForAssign`; `listForAdmin`) + `ContractorController` (admin registry index/store/status) + `MaintenanceService` (`assignToContractor`, `listForContractor`, then the slice-3 `start`/`complete`/`confirm`/`close`) + `Contractor`/`ContractorTrade` models + `MaintenanceAssignedNotification`. Routes: `admin.contractors.{index,store,status}`, `owner.maintenance.assign`, contractor `role` group GET `/contractor/maintenance`; `LoginController::landingFor` sends contractors straight to the job desk.
- **Job life-cycle (slice 3)**: contractor `start` (assigned→in_progress, own-job-only, owner notified) → `complete` (in_progress→completed, required summary, owner + tenant notified) → tenant `confirm` (one-shot, gates close) → owner `close` (sets `resolved_at`, request feeds maintenance spend). Routes `contractor.maintenance.{start,complete}`, `tenant.maintenance.confirm`, `owner.maintenance.close`; notification classes `MaintenanceStartedNotification`, `MaintenanceWorkCompletedNotification`, `MaintenanceTenantConfirmedNotification`, `MaintenanceClosedNotification`.
- **Frontend**: `Admin/Contractors/Index.jsx` (register form + trade rows + transition buttons), `Contractor/Maintenance/Index.jsx` job desk (brief cards + Start + complete-with-note + "Fix confirmed" chip), owner queue `AssignForm` + "Inspect & close" form + tenant-confirmed chip, tenant Confirm-fix button + confirmed chip, "Contractor Registry"/"My Jobs" nav, StatusBadge `unverified`/`vetting`/`verified` tones.
- **Demo data**: `ContractorSeeder` (Bulawayo Plumbing Co. verified + linked to demo `contractor@dzimba.local`; Mura Building Services verified; CleanFlow Pest Solutions vetting) runs before `MaintenanceSeeder` (MR-2026-00001 seeds `assigned` @ $85.00 — all three parties drive the lifecycle from there in the browser).
- **Tests**: OperateTest to 31 (registry, assignment, then full track/close lifecycle + gate matrix + isolation 404s) and access matrix rows for the four new action routes. Full suite 394 passed / 1884 assertions.