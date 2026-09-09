# Module 12 - Subscription Management

> Phase: Phase 2 | Primary actors: Owner (subscriber), Admin (plans & billing), Tenant (no direct role)

## 1. Purpose

The platform's core revenue engine. Owners pay an affordable subscription instead of agent commission. Admin defines plans, pricing and limits; the system bills, tracks expiry, renewal and upgrades/downgrades.

## 2. Roles & Responsibilities

| Actor | Actions |
|---|---|
| **Admin** | Define plans (limits: listings, features, support tier), set pricing/currency, manage promos, issue credits/refunds, view subscription revenue. |
| **Owner** | Choose plan, pay monthly/annually, view usage vs limits, upgrade/downgrade, renew, handle payment failures. |
| **Tenant** | No direct role; plan quality indirectly signals platform trust. |

## 3. Functional Requirements

- Example plans: Free (1 listing), Basic (5), Professional (20, analytics + featured), Business (unlimited, priority support).
- FR-01 Plan catalog: id, name, listing_limit, featured_slots, support_tier, analytics_enabled, price, billing_cycle.
- FR-02 Subscribe a default plan on owner registration (Free).
- FR-03 Active subscription state and remaining listing quota enforced on listing creation.
- FR-04 Renewal cron on cycle end; grace period before limit enforcement.
- FR-05 Upgrade prorates; downgrade defers until cycle end.
- FR-06 Billing history and invoice per payment.
- FR-07 Over-limit UX: blocked publish with upgrade prompt.

## 4. Non-Functional Requirements

- NFR-01 Quota checks are atomic (no two requests publish past the limit).
- NFR-02 Billing timestamps UTC; proration deterministic.
- NFR-03 Failed payment retries with backoff, then plan suspension.

## 5. Workflows & Pseudo Sentences

1. **Onboard** - When a new owner registers, the system auto-subscribes them to the Free plan; when the owner adds a property, the system checks quota; when the limit is reached, the system prompts an upgrade.
2. **Checkout** - When an owner selects a plan, the system creates a subscription with `pending` billing; when payment succeeds, the system activates it from the billing date; then the system issues a receipt.
3. **Renew** - When a subscription cycle ends, the system attempts renewal; when renewal fails, the system enters the grace period; when the grace period lapses, the system suspends extra listings (over Free) but keeps them in draft.
4. **Upgrade** - When an owner upgrades, the system prorates the difference and lifts limits instantly; when the current plan expires at a later date, the system keeps the higher plan and adjusts billing.
5. **Downgrade** - When an owner downgrades, the system marks the current cycle to end at the plan change; at cycle end the system restricts listings above the new quota.

## 6. Data Model

| Table | Key Columns |
|---|---|
| `subscription_plans` | id, name, listing_limit, featured_slots, support_tier, analytics_enabled, price, billing_cycle (monthly/annual), status |
| `subscriptions` | id, owner_id FK, plan_id FK, status (active/grace/suspended/cancelled), starts_at, ends_at, trial_ends_at |
| `subscription_invoices` | id, subscription_id FK, amount, status, invoice_no, paid_at |
| `subscription_history` | id, subscription_id FK, event (subscribed/renewed/upgraded/downgraded/cancelled), details (json), created_at |

Relationships: `subscriptions` belongsTo owner and plan; hasMany invoices and history entries.

## 7. Integrations & Dependencies

- Module 02 (quota on publish), Module 09 (shared gateway), Module 15 (expiry/renewal notices), Module 17 (MRR analytics), Module 18 (plan CRUD).

## 8. Acceptance Criteria

AC-01 Listing creation respects the active plan quota.
AC-02 Renewal/grace/suspension transitions happen automatically on schedule.
AC-03 Upgrade lifts limits immediately; downgrade defers to cycle end.
AC-04 Subscription revenue reconciles with invoices.