# Module 19 - Complaints, Reports & Disputes

> Phase: Phase 2 | Primary actors: Tenant/Owner (reporters), Admin (resolver)

## 1. Purpose

Because agents are removed, trust and accountability become critical. This module captures scam reports, moderation issues and transactional disputes (deposits, rent, maintenance), tracks resolution and produces the evidence trail backing every outcome.

## 2. Roles & Responsibilities

| Actor | Actions |
|---|---|
| **Tenant** | Report a fake property/owner, incorrect info, scam attempt, misleading pricing, user misconduct; open a deposit/rent dispute. |
| **Owner** | Report tenant misconduct/non-payment; respond to disputes with evidence. |
| **Admin** | Triage, investigate, take action (warn/suspend/remove/refund), record resolution, escalate to legal where needed. |

## 3. Functional Requirements

- FR-01 Report types: fake property, fake owner, incorrect information, scam attempt, misleading price, inappropriate listing, user misconduct.
- FR-02 Dispute types (Phase 2): deposit deductions, rent overcharge, maintenance neglect, lease interpretations.
- FR-03 Statuses: `open`, `under_review`, `resolved`, `dismissed`, `escalated`.
- FR-04 Evidence attachments (screenshots, messages from Module 05, documents).
- FR-05 Action log with every admin step.
- FR-06 Automatic moderation: repeat offenders auto-flagged; verified listings reported twice → badge on hold (Module 14).
- FR-07 Resolution templates for standard outcomes.

## 4. Non-Functional Requirements

- NFR-01 Case numbering `DSP-####` / `CM-####` unique.
- NFR-02 Reporter anonymity from the accused until disclosure is required.
- NFR-03 SLA on admin first-response (e.g. 24h for scam, 72h for disputes).

## 5. Workflows & Pseudo Sentences

1. **Report** - When a user reports a listing, the system collects type and evidence; then the system creates a case and notifies admin; when it involves a verified listing, the system pauses the verified badge on hold.
2. **Investigate** - When admin opens the case, the system gathers linked audit data (enquiries, applications, verification docs); when admin requests more info, the system asks the reporter; when evidence is sufficient, the system moves the case to resolution.
3. **Resolve** - When admin decides to remove, the system unpublishes the listing and notifies the owner with the outcome; when admin dismisses, the system notifies the reporter and restores any paused badge.
4. **Dispute (deposit)** - When a tenant disputes a deposit deduction, the system links the deposit record and itemised deduction; when admin rules, the system executes a return or confirms forfeit with the documented basis.
5. **Escalate** - When a case reaches an SLA or involves legal risk, the system escalates; when a noted admin handles it, the system flags the case; the final decision is appended to the case history.
6. **Repeat offenders** - When an owner exceeds report thresholds, the system auto-suspends their listings; when admin reviews, the system reinstates or bans.

## 6. Data Model

| Table | Key Columns |
|---|---|
| `reports` | id, reporter_id FK, subject_type (property/user), subject_id, category, description, status, priority, resolved_by, resolved_at, resolution_note |
| `disputes` | id, dispute_type, source_type (deposit/invoice/lease), source_id, opener_id, opponent_id, status, decision, decided_by, decided_at |
| `case_actions` | id, caseable_type, caseable_id, actor_id FK, action, note, created_at |

Relationships: `reports`/`disputes` polymorphic `caseable` for actions; belong to reported subject.

## 7. Integrations & Dependencies

- Module 14 (badge holds), Module 05 (evidence threads), Module 09 (deposit disputes), Module 18 (admin queue), Module 15 (case updates), Module 20 (documents).

## 8. Acceptance Criteria

AC-01 Every case has a complete action history.
AC-02 Verified badge is paused on report and restored only on dismissal.
AC-03 Resolution updates the underlying record (listing, deposit, badge).
AC-04 Admin SLA breaches are visible on the admin dashboard.