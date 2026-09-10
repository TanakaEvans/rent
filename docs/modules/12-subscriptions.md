# Module 12 - Subscription Management (configuration-driven)

> Phase: Phase 2 | Primary actors: Owner (subscriber), Admin (plans & billing), Tenant (no direct role)
> **Config-driven rule**: every commercial value (grace, proration, suspension behaviour, numbering, cycle length, over-limit copy) reads from `ConfigurationService` (`docs/modules/24-system-configuration.md`), never from code constants.
> **Implementation status:** Wave 4 slices 1–2 are DONE. Slice 1: plans, feature grants, grace/proration/suspension/numbering/cycle-day values and the over-limit message all read config, `SubscriptionPlan::subscriptions()`/`Subscription::plan()` pin the `plan_id` FK. Slice 2 (config conformance): `Subscription::GRACE_DAYS` removed (grace/upgrade/cycle-end behave entirely from config); `upgrade()` branches on `subscriptions.proration.mode` (`charge_difference` charges the plan difference, `credit_new_invoice` credits the unused portion, `apply_at_renewal` defers via `pending_upgrade_to`); `applyCycleEnd()` settles deferred upgrades and downgrades at the boundary; old invoices are never rewritten by config changes. Tracking in `docs/modules/23-implementation-plan.md` §1.5.

## 1. Purpose

The platform's core revenue engine. Owners pay an affordable subscription instead of agent commission. Admin defines plans, pricing, allowances and features in the Configuration Centre; the billing engine bills, tracks expiry, renewal and upgrades/downgrades entirely from configuration.

## 2. Roles & Responsibilities

| Actor | Actions |
|---|---|
| **Admin** | Define plans (dynamic fields, see §6), price/currency, feature grants, grace period, proration mode, suspension behaviour, invoice/receipt numbering, renewal reminders, additional-property pricing, promo codes, credits/refunds; view subscription revenue. |
| **Owner** | Choose plan, pay per billing frequency, view usage vs limits (plan allowance + feature grants), upgrade/downgrade, renew, handle payment failures. |
| **Tenant** | No direct role; plan quality indirectly signals platform trust. |

## 3. Functional Requirements

- FR-01 **Plan catalogue is config**: `name, code, description, price, currency, billing_frequency, billing_interval, trial_days, grace_days (nullable → global), listing_limit, featured_slots, status, start_date, end_date` + a set of Feature-Catalogue grants via `subscription_plan_features`.
- FR-01b **Feature Catalogue**: `PROPERTY_LISTING, PROPERTY_ANALYTICS, ADVANCED_SEARCH, TENANT_MESSAGING, VIEWING_MANAGEMENT, APPLICATION_MANAGEMENT, RENT_COLLECTION, MAINTENANCE, FINANCIAL_REPORTS, FEATURED_LISTINGS, MULTIPLE_USERS, MULTIPLE_BRANCHES, API_ACCESS, DEDICATED_SUPPORT`. Plans grant features; no hard-coded entitlement flags.
- FR-02 Default plan (Free) subscribed lazily on first owner use (no public registration endpoint yet).
- FR-03 Active subscription state + remaining listing quota enforced on property publish (config over-limit message).
- FR-04 Renewal cron on cycle end; **grace period from `subscriptions.grace_period_days`**; suspension behaviour from `subscriptions.suspension.behaviour`.
- FR-05 Upgrade prorates per `subscriptions.proration.mode` (charge_difference / credit_new_invoice / apply_at_renewal); downgrade defers until cycle end.
- FR-06 Billing history and one invoice per settled payment; invoice/receipt sequence from `numbering.*`.
- FR-07 Over-limit UX: blocked publish with the configured upgrade prompt.
- FR-08 Additional property slots are a configurable product (`subscriptions.additional_property.*`: price, frequency, auto-renew, max, count-toward-limit, proration).

## 4. Non-Functional Requirements

- NFR-01 Quota checks are atomic (no two requests publish past the limit).
- NFR-02 Billing timestamps UTC; proration deterministic integer-cent math.
- NFR-03 Failed payment retries with configured backoff, then configured suspension.
- NFR-04 Changes to config apply to new billing cycles only; historical invoices stay unchanged (audited).

## 5. Workflows & Pseudo Sentences

1. **Onboard** - When a new owner first acts, the system lazily subscribes them to the configured default plan (Free); when the owner adds a property, the system checks the plan allowance; at the limit the system returns the configured over-limit message with an upgrade prompt.
2. **Checkout** - When an owner selects a plan, the system creates a subscription per the plan's billing frequency/interval; when payment settles, the system activates it from the billing date and issues a receipt numbered from `numbering.*`.
3. **Renew** - When a subscription cycle ends, the system applies any deferred downgrade; otherwise it enters grace (`grace_period_days` from config); when grace lapses under `subscriptions.suspension.behaviour` it suspends extra listings, hides owned listings from the marketplace (`hide_listings`), turns off premium features (`suspend_premium`) or restricts access (`full_suspend`).
4. **Upgrade** - When an owner upgrades, the billing engine applies `subscriptions.proration.mode`: charges the difference, credits the unused portion and invoices the difference, or defers to renewal; limits lift instantly unless deferred.
5. **Downgrade** - When an owner downgrades, the system marks a `pending_downgrade_to` on the subscription; at cycle end it applies the lower plan and restricts listings above the new allowance. An `apply_at_renewal` upgrade mirrors this with `pending_upgrade_to`, settled at the same boundary.

## 6. Data Model

| Table | Key Columns |
|---|---|
| `subscription_plans` | id, name, code, description, price DECIMAL(12,2), currency, billing_frequency (one_time/daily/weekly/monthly/quarterly/semi_annual/annual/custom), billing_interval, trial_days, grace_days (nullable → config), listing_limit (nullable = unlimited), featured_slots, status (active/archived), start_date, end_date, timestamps |
| `subscription_features` | id, code (unique), label, description, status |
| `subscription_plan_features` | id, plan_id FK, feature_id FK, unique(plan_id, feature_id) |
| `subscriptions` | id, owner_id FK, plan_id FK, status (active/grace/suspended/cancelled), starts_at, ends_at (cycle end), trial_ends_at, cycle (frequency snapshot), details JSON (pending_downgrade_to, pending_upgrade_to, grace_until), timestamps |
| `subscription_invoices` | id, subscription_id FK, amount DECIMAL(12,2), status (pending/paid/cancelled/refunded), invoice_no (config sequence), receipt_no (nullable), paid_at, details JSON, timestamps |
| `subscription_history` | id, subscription_id FK, event (subscribed/renewed/upgraded/downgraded/switched/grace_period/suspended/cancelled), details JSON, created_at |
| `system_configurations` + `configuration_audits` | see Module 24 (grace, proration, suspension, numbering, cycle, reminder, over-limit config) |

Relationships: `subscriptions` belongsTo owner and plan; hasMany invoices and history; plans belongsToMany features.

## 7. Integrations & Dependencies

- Module 24 (Configuration/Billing engine — grace, proration, suspension, numbering, cycle, renewal reminders).
- Module 02 (quota on publish), Module 09 (shared payment gateway/ledger + POP/approval config), Module 15 (renewal/expiry notices per configured reminders), Module 17 (MRR analytics), Module 18 (plan + config centre CRUD).

## 8. Acceptance Criteria

- AC-01 Listing creation respects the active plan allowance (admin-configurable per plan).
- AC-02 Renewal/grace/suspension transitions happen automatically on schedule using configured grace + suspension behaviour.
- AC-03 Upgrade/proration and downgrade deferral follow the configured proration mode; limits lift/restrict accordingly.
- AC-04 Subscription revenue reconciles with invoices.
- AC-05 Grace period, suspension behaviour, invoice/receipt prefixes and over-limit copy all come from configuration — code contains none of these constants.
- AC-06 Changing plan pricing/limits/grace in the Configuration Centre takes effect on new billing cycles while historical invoices keep their original amounts.