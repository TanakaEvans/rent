# Module 14 - Property & Owner Verification

> Phase: Phase 2 (admin workflow) | Primary actors: Admin (verifier), Owner (evidence), Tenant (trust)

## 1. Purpose

Builds the trust that removes the agent's protective role. The platform verifies owners and listings, then displays verified badges. This is a core competitive advantage over social-media property listings.

## 2. Roles & Responsibilities

| Actor | Actions |
|---|---|
| **Admin** | Collect evidence, verify or reject owner/listing claims, set badge status, revoke badges, review reverification requests. |
| **Owner** | Submit identity/ownership evidence (national ID, title deed, rates documents, photos), request verification of owner and of each property, resolve rejections. |
| **Tenant** | Sees Trusted/Verified badge on listings and owner profiles; filters by verified-only; reports suspicious verified listings. |

## 3. Functional Requirements

- FR-01 Owner verification: ID, proof of identity, contact verification (SMS/email code).
- FR-02 Property verification: ownership documents, on-site photo confirmation, admin checklist.
- FR-03 Badge levels: `unverified`, `pending`, `verified`, `revoked`.
- FR-04 Display: `properties.verified` boolean + `verified_owner` derived from user.
- FR-05 Admin queue with evidence viewer, notes and decision.
- FR-06 Re-verification on a schedule (e.g. annually) or on suspicious activity.
- FR-07 Filter: "Verified only" in marketplace (Phase 2).
- FR-08 Scoring: verified owners get search boost.

## 4. Non-Functional Requirements

- NFR-01 Evidence stored privately in Module 20, never on public pages.
- NFR-02 Verification decisions are logged with the acting admin.
- NFR-03 Sensitive documents encrypted at rest.

## 5. Workflows & Pseudo Sentences

1. **Owner verification request** - When an owner submits documents, the system creates a verification case; when the tenant/owner submits the ID, the system queues it for admin; when admin reviews and approves, the system sets the owner's badge to `verified`; then the system shows "Verified Owner" on their profile and listings.
2. **Property verification** - When an owner verifies a property, the system requires ownership evidence; when admin walks the checklist, the system may request a live photo; when approved, the system sets `verified = true`; then the system shows the "Verified Property" badge.
3. **Reject** - When admin rejects, the system records a reason; when the owner fixes and resubmits, the system reopens the case; on repeat failure, the system flags the owner for review.
4. **Revoke** - When a verified listing is proven fake, the admin revokes the badge; the system demotes the listing in search; then it opens a dispute/moderation record.
5. **Filter** - When a tenant filters by verified, the system restricts results to `verified` properties and/or `verified` owners.

## 6. Data Model

| Table | Key Columns |
|---|---|
| `verification_cases` | id, subject_type (owner/property/listing), subject_id, owner_id FK, status, submitted_at, reviewed_by FK, decision_at, decision_note |
| `verification_documents` | id, case_id FK, document_id FK (Module 20), type, created_at |

Relationships: `verification_cases` polymorphic `subject`; belongsTo owner and reviewer; hasMany documents.

## 7. Integrations & Dependencies

- Module 02 (`properties.verified`), Module 18 (admin queue), Module 19 (scam reports → revoke), Module 20 (documents), Module 23 (search boost config).

## 8. Acceptance Criteria

AC-01 Badges reflect the latest decision, not the first one.
AC-02 Evidence is never exposed on public pages.
AC-03 Verified-only filter returns only approved subject(s).
AC-04 Revocation removes the badge and demotes the listing.