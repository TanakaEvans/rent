# Module 06 - Property Viewing Management

> Phase: MVP (flows) | Primary actors: Tenant (requestor), Owner (scheduler), Admin (monitor)

> **Implementation status (Wave 2):** Viewing **slots** and **requests** built. Slots: owner CRUD under `Owner/ViewingSlots/Index`, now a **month calendar** (`Components/Shared/Calendar.jsx`) with a day panel — pick a day, add/edit/remove slots, and see pending tenant requests on the same grid (Open/Booked/Request/Past legend). Backing store `viewing_slots` with starts_at/ends_at/status, `starts_at after:now` / `ends_at after:starts_at`, owner isolation 404. Requests: `viewing_requests` (requested/accepted/rescheduled/**declined**/completed/cancelled/no-show — `declined` added to cover owner reject in FR-03); `ViewingRequestService` enforces a per-status transition machine (409 on illegal moves), locks the slot on **accept** (`taken`, double-book 409), waits for tenant **confirm** after owner **reschedule** (proposes a new slot, releases the old one), and releases a locked slot on **cancel**/**no-show**/**decline**. **Tenant-suggested times (Sep 2026):** `slot_id` is now nullable; a tenant can propose their own `proposed_starts_at`/`proposed_ends_at` (one open suggestion per property) via `ViewingRequestService::propose`, and when the owner accepts a suggestion the service **creates and locks a slot** from those times. Tenants book from the public detail page via `Components/Shared/ViewingBooker.jsx` (toggle **Open times** ↔ **Suggest a time**); both parties have list pages (`Tenant/Viewings` with a schedule calendar, `Owner/Viewings/Index`) that render each request's window from the slot **or** the proposed times and flag tenant-suggested ones; confirmed in `EngageTest` + access matrix. **Location reveal:** once a viewing is accepted the tenant sees the exact pin and a **Get directions** link (see `docs/modules/03-marketplace-search.md` location privacy). **Post-viewing next step (Oct 2026):** a completed viewing shows the tenant two follow-ups — **Apply to rent** (links to the property's apply form) or **Not interested** (`tenant.viewings.not-interested`, records `outcome = not_interested`; 409 unless completed, 404 on another tenant's booking). The owner's list shows "Tenant passed after viewing" so the loop is closed either way. **Notifications** on these events were raised in the Wave 2 notifications slice (`ViewingRequestedNotification` on request → owner, `ViewingAcceptedNotification`/`ViewingRescheduledNotification` on accept/reschedule → tenant; confirm/cancel/decline stay silent). See `docs/modules/15-notifications.md`.

## 1. Purpose

Manages the physical property-viewing step between a tenant and an owner - a business-critical handoff that agents traditionally ran. Scheduling, reminders and history remove the coordination burden and build a paper trail.

## 2. Roles & Responsibilities

| Actor | Actions |
|---|---|
| **Tenant** | Request a viewing, pick a suggested slot, reschedule/cancel own requests, get confirmations and reminders. |
| **Owner** | Define available slots, accept/reject/reschedule requests, record viewing outcomes. |
| **Admin** | Monitor missed-viewing abuse, resolve scheduling disputes, configure reminder templates. |

## 3. Functional Requirements

- FR-01 Tenant requests a viewing by taking an owner slot **or** suggesting their own start/end time, with an optional message. Only one open suggestion per property at a time.
- FR-02 Owner maintains a set of available slots per property on a calendar.
- FR-03 Owner accepts, rejects or proposes a new slot. Accepting a tenant-suggested time creates and locks a slot from it.
- FR-04 Statuses: `requested`, `accepted`, `rescheduled`, **`declined`**, `completed`, `cancelled`, `no-show`. (`declined` extends the spec's release list so an owner can explicitly reject a request; the slot stays open.)
- FR-05 Confirmations and reminders emitted (in-app/email/SMS) to both parties on schedule.
- FR-06 Viewing history retained per property and tenant (owner uses it to shortlist serious applicants).

## 4. Non-Functional Requirements

- NFR-01 Anti-double-booking: a slot may only accept one viewing.
- NFR-02 Reminders computed from local timezone settings.
- NFR-03 No-shows recorded to reputation scoring (Phase 3).

## 5. Workflows & Pseudo Sentences

1. **Request** - When a tenant requests a viewing, the system stores the request with default status `requested`; then the system sends the owner a notification with the proposed slot; finally the system adds the pending request to the owner's viewing calendar.
2. **Confirm** - When the owner accepts a slot, the system marks the slot taken; then the system notifies the tenant with directions and owner contact; when the reminder time arrives, the system texts/emails both parties.
3. **Reschedule** - When either party requests a change, the system reverts the slot to `requested`; when the owner proposes a new time, the system notifies the tenant to confirm.
4. **Complete** - When the viewing happens, the owner marks it `completed`; then the system prompts the owner to log a short outcome (liked/escalated/declined); when they proceed to apply, the system prefills the application with viewing context.
5. **No-show** - When a tenant does not attend, the owner marks `no-show`; the system records it against the tenant's profile.

## 6. Data Model

| Table | Key Columns |
|---|---|
| `viewing_slots` | id, property_id FK, owner_id FK, starts_at, ends_at, status (available/taken) |
| `viewing_requests` | id, property_id FK, tenant_id FK, slot_id FK **(nullable)**, proposed_starts_at, proposed_ends_at, status (requested/accepted/rescheduled/declined/completed/cancelled/no-show), request_message, outcome, timestamps |

Relationships: `viewing_slots` belongsTo `properties`; `viewing_requests` belongsTo property, tenant and slot (one-to-one slot). When `slot_id` is null the request carries the tenant's own `proposed_starts_at`/`proposed_ends_at`; `ViewingRequest::window()` returns the effective start/end from either source, and accepting a suggestion materialises a locked slot.

## 7. Integrations & Dependencies

- Module 05 (enquiries), Module 07 (applications), Module 15 (notifications/reminders), Module 16 (owner calendar widget).

## 8. Acceptance Criteria

AC-01 A slot cannot be double-booked.
AC-02 Tenant receives confirmation and reminder on accepted viewings.
AC-03 Both parties can view the full viewing history for a property.