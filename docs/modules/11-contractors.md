# Module 11 - Service Provider / Contractor Management

> Phase: Phase 2 | Primary actors: Owner (hirer), Contractor (worker), Admin (registry)

## 1. Purpose

A registry of verified tradespeople (plumbers, electricians, builders, etc.) that owners assign maintenance jobs to. Becomes a marketplace of its own and a revenue stream through contractor subscriptions or job fees.

## 2. Roles & Responsibilities

| Actor | Actions |
|---|---|
| **Admin** | Register/verify contractors, manage trades/services, mediate ratings disputes, run contractor blacklists. |
| **Owner** | Browse verified contractors, assign jobs, approve quotes, rate completed work. |
| **Contractor** | Maintain profile and trades, receive job briefs, accept jobs, submit quotes, mark work done, respond to feedback. |

## 3. Functional Requirements

- FR-01 Contractor profile with trades/services, service areas, contact, documents (licence, insurance), photos.
- FR-02 Verification status: `unverified`, `vetting`, `verified`, `suspended`.
- FR-03 Contractors assigned to maintenance jobs (Module 10) with cost and timeframe.
- FR-04 Job history and ratings per contractor.
- FR-05 Owner can shortlist favourite contractors.
- FR-06 Phase 3: contractor directory publicly searchable; contractors subscribe to receive jobs.

## 4. Non-Functional Requirements

- NFR-01 Ratings are computed from completed jobs only (no self-rating).
- NFR-02 A contractor cannot be assigned the same job twice simultaneously.
- NFR-03 Licensing documents stored in Module 20 and required for `verified` status.

## 5. Workflows & Pseudo Sentences

1. **Register** - When a contractor applies, the system stores the profile with status `vetting`; when admin verifies documents, the system sets status `verified`; then the system makes them visible to owners in their service area.
2. **Assign** - When an owner assigns a job, the system creates the job with agreed quote and window; when the contractor accepts, the system locks the assignment and notifies the tenant.
3. **Complete & rate** - When the contractor marks the job complete, the owner inspects and closes; when the owner rates the contractor, the system recomputes the average and confidence score.
4. **Suspend** - When admin suspends a contractor, the system blocks new assignments; when the open jobs finish, the system marks the profile `suspended`; an owner can appeal on the contractor's behalf.

## 6. Data Model

| Table | Key Columns |
|---|---|
| `contractors` | id, user_id FK (nullable), business_name, contact, service_area (json), status (unverified/vetting/verified/suspended), rating_avg, jobs_completed, timestamps |
| `contractor_trades` | id, contractor_id FK, trade, rate, timestamps |
| `maintenance_jobs` | see Module 10 `maintenance_requests.assigned_contractor_id` + `maintenance_actions` |

Relationships: `contractors` belongsTo user (optional); hasMany trades and jobs.

## 7. Integrations & Dependencies

- Module 10 (jobs), Module 15 (job notifications), Module 17 (contractor performance), Module 20 (documents), Module 19 (rating disputes).

## 8. Acceptance Criteria

AC-01 Only verified contractors are assignable to jobs.
AC-02 Ratings reflect completed jobs only.
AC-03 A suspended contractor receives no new assignments.