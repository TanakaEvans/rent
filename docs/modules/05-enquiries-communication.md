# Module 05 - Enquiries & Communication

> Phase: MVP (flows) | Phase 2 (built-in messenger) | Primary actors: Tenant (initiator), Owner (responder), Admin (auditor)

## 1. Purpose

Replaces the traditional agent intermediary: tenants ask questions directly to the property owner through the platform, and owners respond. The platform keeps the thread, history and evidence for trust and dispute handling.

## 2. Roles & Responsibilities

| Actor | Actions |
|---|---|
| **Tenant** | Enquire about a property (message body), ask availability questions, track status of their enquiry, follow up. |
| **Owner** | Receive enquiries on the dashboard, reply, mark handled, convert to a viewing request or application. |
| **Admin** | Audit communication history for disputes, moderate abuse, archive stale threads. |

## 3. Functional Requirements

- FR-01 Enquiry form on property detail: message (≤1000 chars) + optional contact phone.
- FR-02 Enquiry created against `(property_id, tenant_id)`.
- FR-03 Owner inbox groups enquiries by property.
- FR-04 Statuses: `new`, `read`, `replied`, `closed`.
- FR-05 Push a notification to the owner (in-app/email/SMS) instantly on creation.
- FR-06 Owner can convert an enquiry into a formal application or viewing request.
- FR-07 Phase 2: threaded real-time messaging between owner and tenant for the life of the enquiry and tenancy.

## 4. Non-Functional Requirements

- NFR-01 Message content validated and length-limited.
- NFR-02 Message history immutable; edits logged (audit).
- NFR-03 Anti-abuse: rate-limit enquiries per tenant per property.

## 5. Workflows & Pseudo Sentences

1. **Enquire** - When a logged-in tenant submits an enquiry, the system validates the message; then the system stores the enquiry linked to the property and tenant; after that the system notifies the owner; finally the system shows the tenant "Enquiry sent" with a thread link.
2. **Respond** - When an owner opens the inbox, the system marks enquiries `read`; when the owner replies, the system stores the reply and notifies the tenant; when the owner sets it `closed`, the system archives the thread.
3. **Convert** - When an owner clicks "Request application", the system creates a draft for a rental application prefilled with the enquiry context; when an owner clicks "Schedule viewing", the system opens a viewing request (Module 06).
4. **Dispute evidence (Phase 3)** - When a dispute is opened, the system exports the full immutable thread; when the case closes, the system tags the thread as `evidence-archived`.

## 6. Data Model

| Table | Key Columns |
|---|---|
| `enquiries` (MVP) | id, property_id FK, tenant_id FK, message, status (new/read/replied/closed), timestamps |
| `enquiry_messages` (Phase 2) | id, thread_id FK, sender_id FK, body, sent_at, seen_at |
| `enquiry_threads` (Phase 2) | id, property_id, tenant_id, owner_id, status, timestamps |

Relationships: `properties` hasMany `enquiries`; `auth_users` (tenant) hasMany `enquiries`; `enquiries` belongsTo property and tenant.

## 7. Integrations & Dependencies

- Module 02 (property), Module 06 (viewings), Module 07 (applications), Module 15 (notifications), Module 19 (dispute evidence).

## 8. Acceptance Criteria

AC-01 An enquiry reaches the owner dashboard within the notification channel configured.
AC-02 A tenant can track their enquiry status.
AC-03 Owners cannot see other owners' enquiry threads (row-level isolation).

## 9. Implementation Status

- **Implemented (Wave 2 slice 1):** FR-01 (message ≤1000 + optional phone), FR-02, FR-03 (inbox grouped by property), FR-04 (statuses new/read/replied/closed with explicit transitions), NFR-01, NFR-03 (one open enquiry per tenant per property), AC-02, AC-03. FR-05 landed with the in-app notifications slice (`NewEnquiryNotification` to the owner on create, `EnquiryRepliedNotification` to the tenant on reply — see `docs/modules/15-notifications.md`); FR-06 conversion waits for M6/M7; FR-07 is Phase 2.
- `enquiries` table (id, property_id, tenant_id, message 1000, phone nullable, reply nullable, replied_at, status, read_at, timestamps). `EnquiryService` guards ownership in `openForOwner` (owner_id check on the eager-loaded property → 404).
- Tenant form on the public detail page (tenant-only; guests get the login CTA). Owner inbox + thread page; tenant thread list; nav + dashboard counts for both roles. Demo enquiry seeded in `PropertySeeder`.
- Tests: `tests/Feature/EngageTest.php` (15 tests) + access matrix entries for all enquiry routes.