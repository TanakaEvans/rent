# Module 15 - Notification Management

> Phase: MVP (in-app foundation) | Phase 2 (email/SMS/WhatsApp) | Primary actors: all 3 parties

> **Implementation status (Wave 2 slice 4):** **In-app notifications built** (closes Wave 2). The framework `notifications` table was NOT present in the base user migration, so it was added via `2026_09_09_000010_create_notifications_table` (uuid `id`, `type`, `morphs('notifiable')`, `data` json, nullable `read_at`, timestamps). Five notification classes send via the database channel and carry a `{title, body, link}` payload for deep-linking: `NewEnquiryNotification` + `EnquiryRepliedNotification` (`EnquiryService::create` → property owner; `reply` → enquiry tenant), `ViewingRequestedNotification` (request `create` → owner), `ViewingAcceptedNotification` (`accept` → tenant), `ViewingRescheduledNotification` (`reschedule` → tenant). Confirm/cancel/decline viewing actions deliberately stay silent. `NotificationController` provides `notifications.index` (`Notifications/Index` page), `notifications.read` (blocks non-owners with 404, marks read, redirects to the payload link), `notifications.read-all`. Routes sit in the app-wide authenticated group (any role). `HandleInertiaRequests` shares the latest 8 notifications + `unreadNotificationsCount`; the header bell in `MainLayout` shows the badge + dropdown (click = read + deep-link) and links to the inbox page. Tested in `EngageTest` (event→row per type, unread count, deep-link redirect, 404 isolation, read-all) + access matrix (any authenticated role OK, guest redirects to login).
> **Wave 3 additions (M7/M8):** six events added — `NewApplicationNotification` (apply → owner), `ApplicationApprovedNotification` (approve → tenant), `ApplicationRejectedNotification` (reject → tenant), `LeaseCreatedNotification` (lease generated → tenant, deep-links to `tenant.leases.index`), `LeaseSentForSignatureNotification` (lease sent → tenant, deep-links to `tenant.leases.index`), and `LeaseSignedNotification` (both signed → the counterpart, deep-links to that party's lease list). All deep-link to `owner.applications.index` / `tenant.applications.index` / `tenant.leases.index` / `owner.leases.index`; the recorded reject reason is surfaced on the tenant applications page. Others: shortlist, confirm/cancel/decline, viewing no-show/complete, and the "request more info" step stay silent for now.
> Still to come: email/SMS channels, preferences (FR-03/AC-03), delivery logs + retry (FR-05), priority tiers (FR-06), escalation (FR-07).

## 1. Purpose

The central notification bus that pushes events to the right person through the right channel, keeping tenants, owners and admins informed without human coordination - replacing the agent's role as message broker.

## 2. Roles & Responsibilities

| Actor | Actions |
|---|---|
| **Admin** | Configure channels and templates, throttle policy, opt-in defaults, broadcast system notices. |
| **Owner** | Receive new-enquiry, application, viewing, rent, maintenance and subscription notifications; manage own preferences. |
| **Tenant** | Receive application status, viewing confirmation, rent-due, renewal and platform notices; manage own preferences. |

## 3. Functional Requirements

- FR-01 Event types: new enquiry, new application, viewing request/confirmation/reminder, application approved/rejected, rent due/overdue, payment received, lease expiring, maintenance reported/completed, subscription expiring/payment failed, new matching property (saved search).
- FR-02 Channels: in-app bell, email, SMS, WhatsApp (Phase 2).
- FR-03 Per-user channel preferences with global defaults from admin.
- FR-04 In-app inbox with read/unread and per-item links.
- FR-05 Delivery logging and retry with backoff for failed channels.
- FR-06 Priority tiers: emergency → SMS+push; routine → email+in-app.
- FR-07 Forwarding: some notifications forward to admin when SLA breached.

## 4. Non-Functional Requirements

- NFR-01 Rate-limiting to prevent spam bursts (e.g. max N emails per hour per user).
- NFR-02 Template renders for each channel; channel failure never blocks the workflow.
- NFR-03 Preference changes apply atomically (no duplicate sends mid-change).

## 5. Workflows & Pseudo Sentences

1. **Dispatch** - When any workflow raises an event, the system resolves the actor and channel per preferences; then the system renders the template and queues delivery; when delivered, the system logs the notification with `sent`; when it fails, the system retries with backoff.
2. **Inbox** - When a user opens the bell, the system lists unread first; when the user opens an item, the system marks it read and deep-links to the source screen.
3. **Prefs** - When a user edits preferences, the system persists channel toggles; when a future event fires, the system honours the new settings.
4. **Escalation** - When an emergency event fires, the system bypasses quiet hours and uses SMS/push; when the SLA breaches, the system adds the admin to the recipient list.
5. **Broadcast** - When admin issues a system notice, the system targets a segment (all/owner/tenant/city); the channel defaults to in-app + email.

## 6. Data Model

| Table | Key Columns |
|---|---|
| `notifications` | id, notifiable_id, notifiable_type, type, data (json), read_at, created_at |
| `notification_preferences` | id, user_id FK, event_type, channels (json), created_at |
| `notification_logs` | id, notification_id FK, channel, status (queued/sent/failed), delivered_at, error |

(The framework `notifications` table was added manually as `2026_09_09_000010` — it is NOT shipped by the base user migration. `notification_preferences` and `notification_logs` are Phase 2.)

Relationships: polymorphic to users; preferences belongTo user.

## 7. Integrations & Dependencies

- Every workflow module (04-14) raises events; Module 16/17 surfaces notification stats; Module 12 uses expiry notices.

## 8. Acceptance Criteria

AC-01 Events dispatch to the correct actor and channel.
AC-02 Failed channel delivery retries without blocking the workflow.
AC-03 Preferences are honoured on the next event.
AC-04 Emergency events skip quiet hours and escalate on SLA breach.