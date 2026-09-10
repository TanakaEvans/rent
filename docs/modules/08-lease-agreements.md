# Module 08 - Lease & Agreement Management

> Phase: MVP | **Implemented (Wave 3 slices 2-5)** | Primary actors: Owner (creator), Tenant (signer), Admin (templates & storage)

> **Implementation status:** **Slice 2 (create) — DONE.** `leases` + `lease_history` tables; `Lease` state machine (`draft→sent→signed→active→renewed→terminated`, `terminated` terminal); `LeaseService::createFromApplication` generates a lease from an **approved** application (approved-only guard, one lease per application/property, prefilled tenant/property/rent/deposit, unique `lease_no` `LSE-<year>-NNNN`, default monthly payment terms, clause v1, `lease_created` history entry), flips the property to **`reserved`** (FR-05 / AC-01 handover — the step deferred from the applications slice) and **auto-rejects remaining active applicants** with the recorded reason + notification. `LeaseController` + routes `owner.applications.lease` (POST from the approved application card), `owner.leases.index`, `tenant.leases.index`. UI: `Owner/Leases/Index` + `Tenant/Leases` (status prose per state) + nav for both roles + `StatusBadge` `sent/signed/renewed/terminated` tones. Notified via `LeaseCreatedNotification`. Tested in `CommitTest` + access matrix.
> **Slice 3 (signing) — DONE.** `lease_signatures` table (`2026_09_09_000013`, unique per lease×user, signed_at + signature_payload varchar(255)); `LeaseService::sendForSignature` (owner sends only a draft → `sent`, history `lease_sent`, tenant notified) and `LeaseService::sign` (parties only — owner-of-property or lease tenant, else 404; must be `sent`; one signature per user; empty payload defaults to signer name; when **both** sign the lease moves `sent→signed→active` in one transaction and the property moves `reserved→occupied` + the counterpart is notified via `LeaseSignedNotification`). Routes `owner.leases.send`, `owner.leases.sign`, `tenant.leases.sign`. UI: Send-for-signature + inline SignPad (`Components/Shared/LeaseSignPad`) for each party + signature chips (who/when) on both lease lists. 11 new tests in `CommitTest` (send rules, parties-only 404s, senting rules, no double-sign, both-sign→active+occupied). FR-03/AC-02/AC-03 covered.
> **Slice 4 (renew, FR-05) — DONE.** `2026_09_09_000014` adds `renewed_from_id` (nullable self-FK to leases, nullOnDelete + index). `LeaseService::renew` — owner-only via `findOwnedByOwner`, **only an `active` lease renewable**; copies property/tenant/rent/deposit/payment_terms/clause_version (`application_id` null); default `start_date` = old lease `end_date`, default `end_date` = start + 1y; `end > start` validated; the new lease starts `draft`, is linked via `renewed_from_id`, writes `lease_renewal` history on itself and `lease_renewed` history on the source (the old lease also gains constraint `renewed-leases-not-renewable` + a one-in-flight renewal guard). `sign()` activation now also **marks the source lease `renewed`** (history `lease_renewed` + `superseded_by` details) and skips the occupancy flip when the property is already `occupied`. Route `owner.leases.renew` (POST) via `LeaseController::renew`. UI: `Owner/Leases/Index` renew form (inline date pickers, open-per-lease) + renewal-linkage chips; `Tenant/Leases` linkage chips + renewed prose. Seeded demo chain: active `LSE-DEMO-2026-003` + draft renewal `LSE-DEMO-2026-002` on the (now occupied) Kumalo property. 60/30/14-day expiry reminders deferred to Wave 4 (Notifications growth). 8 new tests in `CommitTest` (copied terms, dates, only-active, cross-owner 404, one-in-flight, renewed-not-renewable, signing activates + marks source renewed, tenant page exposes linkage) + access matrix (tenant 403, guest redirect). FR-05 / workflow 3 covered.
> **Slice 5 (document store, M20-lite / FR-07) — DONE.** `documents` table (`2026_09_09_000015`) + `Document` model + `Lease::document()`. `DocumentService::renderAgreementText` renders the authoritative plain-text agreement snapshot (parties, property, term, rent/deposit, payment terms, every signature payload + signed_at, clause version); `storeLeaseAgreement` upserts one row per lease and bumps `version` in place (full `document_versions` history + binary/pdf storage deferred to full M20). The service is hooked into `LeaseService::sign()` so **every activated lease stores its agreement** (fresh and renewal alike). `DocumentController` + 4 routes: `owner.documents.index`, `tenant.documents.index` (role groups), `documents.show` / `documents.download` (app-wide auth; download streams `text/plain` attachment, versioned filename). Access: admin/superuser OR lease property owner OR lease tenant; everyone else 404. UI: `Owner/Documents/Index` + `Tenant/Documents` folder cards + shared `Documents/Show` snapshot page + FolderOpen nav entries + "View agreement / Download" links on lease cards. Seeded demo document for active `LSE-DEMO-2026-003` (guarded existence check). 10 new tests in `CommitTest` (store-on-activation with signatures, renewal stores its own, party view/download, cross-party 404s, admin access, scoped indexes, version bump, guest redirect) + access matrix +8. Full suite 189 passed. FR-07 / AC-04 covered.

## 1. Purpose

Formalises an approved application into a rental agreement. Manages lease creation from templates, digital signing, the lease lifecycle (renewal/termination) and the authoritative document record.

## 2. Roles & Responsibilities

| Actor | Actions |
|---|---|
| **Owner** | Create lease from template, specify rent/deposit/dates/terms, send for signature, finalise, renew or terminate. |
| **Tenant** | Review lease, sign digitally, access copy anytime, receive renewal reminders. |
| **Admin** | Maintain lease templates and clause library, ensure compliant terms, archive terminated leases (Module 20). |

## 3. Functional Requirements

- FR-01 Generate lease from an approved application (prefilled: names, property, rent, deposit, dates).
- FR-02 Lease fields: start/end dates, rent amount, deposit, payment terms, notice period, clause version.
- FR-03 Digital signing flow for both parties with timestamps.
- FR-04 Statuses: `draft`, `sent`, `signed`, `active`, `renewed`, `terminated`.
- FR-05 Lease renewal: creates a new lease period linked to the original.
- FR-06 Lease termination: records reason, exit date and final inspection notes.
- FR-07 Lease documents stored in Module 20 document repository.

## 4. Non-Functional Requirements

- NFR-01 Time-stamped signature audit trail.
- NFR-02 Clause versioning so template changes are tracked.
- NFR-03 Sensitive data (IDs) encrypted at rest where applicable.

## 5. Workflows & Pseudo Sentences

1. **Hold/reserve conversion** - When a lease is created from an approved application, the system prefills parties and property from the application; the system keeps property status `reserved` until the lease is signed.
2. **Sign** - When the owner sends the lease, the system notifies the tenant to review; when the tenant signs first, the system requests the owner's counter-signature; when both have signed, the system sets status `active` and marks the property `occupied`.
3. **Renew** - When the owner creates a renewal on an `active` lease, the system copies terms with updated dates and creates a linked `draft`; when both sign the renewal, the system sets it `active` and marks the original lease `renewed`. (60/30/14-day expiry reminders deferred to Wave 4.)
4. **Terminate** - When either party gives notice, the system records the termination date; when the owner closes the lease, the system runs a final invoice/balance check; then the system sets status `terminated` and the property returns to `available`.

## 6. Data Model

| Table | Key Columns |
|---|---|
| `leases` | id, property_id FK, tenant_id FK, application_id FK (nullable), lease_no (unique), start_date, end_date, rent_amount, deposit_amount, payment_terms (json), status, clause_version, **renewed_from_id FK (nullable, self-referencing)**, timestamps |
| `lease_signatures` | id, lease_id FK, user_id FK, signed_at, signature_payload (text/hash) |
| `lease_history` | id, lease_id FK, action, performed_by FK, details (json), created_at |
| `documents` (M20-lite) | id, lease_id FK, type, name, content (snapshot text), mime, size, version, visibility, timestamps |

Relationships: `leases` belongsTo property, tenant, application; hasMany signatures and history; belongsTo `renewedFrom` and hasMany `renewals` (self-referencing via `renewed_from_id`); hasOne `document` (stored agreement).

## 7. Integrations & Dependencies

- Module 07 (application), Module 09 (rent schedules), Module 19 (disputes on terms), Module 20 (documents), Module 15 (reminders).

## 8. Acceptance Criteria

AC-01 A lease can only be created for a `reserved` or `available` property. — ✅ DONE (slice 2: approved application → lease → `reserved`).
AC-02 Both signatures are timestamped before the lease is `active`. — ✅ DONE (slice 3: `lease_signatures` signed_at; lease only leaves `sent` when both have signed).
AC-03 Activation flips the property to `occupied`. — ✅ DONE (slice 3: sign → `active` + property → `occupied`).
AC-04 Lease documents are retrievable from the tenant's and owner's portals. — ✅ DONE (slice 5, M20-lite: `documents` snapshot auto-stored on activation; `Owner/Documents/Index` + `Tenant/Documents` + shared `Documents/Show` + download).