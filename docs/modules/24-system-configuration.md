# Module 24 - System Configuration & Billing Engine (Configuration-Driven Platform)

> Phase: Phase 2 (foundation of the Monetise wave) | Primary actors: Admin (configure), all engines (consume), Owner/Tenant (feel the effect)
>
> **Guiding principle (source): "Staff should not need developers to change a price, billing cycle, approval requirement, grace period, property limit, invoice rule, suspension behaviour, or notification period." — `docs/modules/Staff should not need developers to chan.md`**

## 1. Core Design Principle

**Configuration-driven, not code-driven.** Every commercial rule has **exactly one source of truth** in the database; every engine reads through `ConfigurationService`. When an admin changes a value, that value is used automatically for:

- New subscriptions + renewal invoices
- Upgrade/downgrade (proration) calculations
- Reports, statements, receipts, payment verification, revenue reports

Example: changing `Professional` from $30/mo → $35/mo must flow everywhere **without a deploy**. What must **never** change is history: an old $30 invoice stays $30.

## 2. Subsystem Architecture

```
                  ADMIN CONFIGURATION (Configuration Centre UI)
                             │
                             ▼
                     CONFIGURATION ENGINE
              (system_configurations + configuration_audits
               + ConfigurationService: cached, typed, audited)
                             │
             ┌───────────────┼────────────────┐
             ▼               ▼                ▼
      BILLING ENGINE   APPROVAL ENGINE   RULE ENGINE
             │               │                │
             ▼               ▼                ▼
      SUBSCRIPTIONS      APPROVALS       AUTOMATED JOBS
             │                              (cron: expiry, reminders,
             ├──────────────┐                featured expiry, reqs)
             ▼              ▼
       INVOICES          PAYMENTS
             │              │
             ▼              ▼
        RECEIPTS        VERIFICATION
             │
             ▼
        STATEMENTS
             │
             ▼
        REPORTING
```

Rule: **no financial or premium behaviour is hard-coded when it can reasonably be configuration.**

## 3. Configuration Centre

The admin ⚙️ Configuration Centre groups every setting. Groups land in waves; the Monetise wave (Wave 4) ships the **billing/subscriptions/financial** groups first.

| Group | Settings (key → example) |
|---|---|
| `general` | platform_name, platform_email, support_email/phone, address, currency, default_timezone, date_format, number_format, financial_year_end, maintenance_mode |
| `registration` | enabled, tenant_enabled, landlord_enabled, email_verification_required, phone_verification_required, admin_approval_required, documents_required, terms_required |
| `subscriptions` | **default_plan** (Free), **grace_period_days** (7), **suspension.behaviour** (`keep_listings`/`hide_listings`/`suspend_premium`/`full_suspend`), **proration.mode** (`charge_difference`/`credit_new_invoice`/`apply_at_renewal`), **renewal.reminders** (JSON `[30,14,7,3,1]`), **cycle_days** (JSON map: monthly→30, annual→365), **available_quota_filter** (`available`), additional_property.price/billing_frequency/auto_renew/max_per_owner, entitlement_over_limit_message |
| `numbering` | invoice.prefix, invoice.padding, invoice.start, invoice.year_reset, receipt.prefix, payment.prefix, statement.prefix, subscription.prefix |
| `payments` | methods[] (bank/mobile/online/manual enabled+details), pop.required/expected_types/max_size/expiry, approval.thresholds (JSON range→role), retry.backoff |
| `invoices` | due_terms_days, tax.enabled/name/percent/mode, regenerate_services[] (subscription_new/renewal/additional_featured/verification), timing (immediate/x_days_before/billing_date/after_approval) |
| `late_fees` | enabled, type (fixed/percent), value, cap, applied (one_time/daily/weekly/monthly) |
| `discounts` / `promos` | discount engine (fixed/percent, scopes, windows), promo code fields (max_uses, per_user_limit, eligible_plans, min_amount) |
| `refunds` / `credits` | allowed, approval_required, full_or_partial, window, cancellation_fee, processing_fee; credit expiry/usage, manual/promotional/refund credit |
| `featured` | price, duration, max_per_property, placement types (homepage/search), auto_expiry, categories/locations |
| `verification` | owner.required/documents/fee/expiry/reverification_period, property.same |
| `listings` | max_photos, max_videos, listing_duration, renewal, required_fields, moderation/approval_policy |
| `tenant` | free_account, enquiry_limits, viewing_limits, application_limits, saved_searches, notification defaults |
| `applications` | fee, validity_days, required_documents, max_applications, owner_approval, admin_approval, withdrawal_rules |
| `viewings` | fee, max_requests, cancellation_window, reschedule_rules, confirmation, reminder_timing, no_show_rules |
| `notifications` | event→channels map, per-event timing (immediately / days-before), templates (admin-editable, placeholders `{{customer_name}}`, `{{invoice_number}}`, `{{amount}}`, `{{expiry_date}}` …) |

### 3.1 Configuration data model

- **`system_configurations`**: `id`, `group` (indexed), `key` (unique, namespaced `group.key`), `type` (`string/integer/boolean/decimal/json` — service casts on read), `value` (`text`), `label`, `description`, `risk` (`low/medium/high/critical` — drives change approval, §5), `is_editable`, `status` (`active/inactive`), timestamps.
- **`configuration_audits`**: `id`, `configuration_id` FK (nullable, kept when the key is deleted), `key`, `old_value`, `new_value`, `changed_by` FK (null on system), `reason`, `approved_by` FK nullable, `effective_from`, `created_at`. **Every change writes a row** (old → new, who, when, why). No audit, no change.

### 3.2 ConfigurationService (single source of truth)

- `get(string $key, $default)` — typed, **cache-backed** (invalidate on set), falls back to sensible defaults when a key is absent so fresh installs/seeds always behave.
- `getGroup(string $group): array`.
- `set(string $key, mixed $value, User $changer, ?string $reason = null)` — validates, writes the row **and the audit row**, invalidates cache. High/critical risk keys require `approved_by` (approval record) before they apply.
- `reset(string $key)` — removes the row, returns to default, audited.

Getters used across the codebase (billing/rule engines) instead of constants: grace days, proration mode, suspension behaviour, numbering prefixes/padding, cycle-day map, reminder schedule, approval thresholds, late-fee config.

## 4. Subscription Plan Configuration (dynamic)

Plans are fully configurable, not fixed columns. A plan is: `name`, `code`, `description`, `price`, `currency` (base currency), `billing_frequency` (`one_time/daily/weekly/monthly/quarterly/semi_annual/annual/custom`) + `billing_interval` (multiplier), `trial_days`, `grace_days` (nullable → global `subscriptions.grace_period_days`), `listing_limit` (property allowance, nullable = unlimited), `featured_slots`, `status` (`active/archived`), `start_date`/`end_date` (sale window). Each plan **grants a set of features** from the Feature Catalogue via the `subscription_plan_features` pivot.

### 4.1 Feature Catalogue

Central, add/edit by admin; plans grant features; entitlement checks read the pivot (never hard-coded flags):

`PROPERTY_LISTING`, `PROPERTY_ANALYTICS`, `ADVANCED_SEARCH`, `TENANT_MESSAGING`, `VIEWING_MANAGEMENT`, `APPLICATION_MANAGEMENT`, `RENT_COLLECTION`, `MAINTENANCE`, `FINANCIAL_REPORTS`, `FEATURED_LISTINGS`, `MULTIPLE_USERS`, `MULTIPLE_BRANCHES`, `API_ACCESS`, `DEDICATED_SUPPORT`. Demo plans grant: Free→PROPERTY_LISTING + TENANT_MESSAGING + VIEWING_MANAGEMENT + APPLICATION_MANAGEMENT; Basic adds FEATURED_LISTINGS; Professional adds PROPERTY_ANALYTICS + FINANCIAL_REPORTS + ADVANCED_SEARCH; Business adds RENT_COLLECTION + MAINTENANCE + MULTIPLE_USERS + DEDICATED_SUPPORT.

### 4.2 Property limits & additional properties

- Enforced automatically at publish (see Module 12). Over-limit message is config (`subscriptions.entitlement_over_limit_message`).
- `subscriptions.additional_property.*`: price per extra slot, billing frequency, auto-renew, whether extras count toward the plan limit, max extras — feeds the planned "purchase an additional property slot" flow.

## 5. Change Approval (risk tiers)

| Risk | Example | Behaviour |
|---|---|---|
| low | notification template | immediate |
| medium | property listing limit | immediate or optional approval (config) |
| high | subscription price | requires `approved_by` on the audit row |
| critical | payment/bank details | requires senior approval |

High/critical changes apply only once the audit `approved_by` is set (`ConfigurationService::set(..., approveAs: ...)`), which the Configuration Centre exposes as a pending-approvals queue.

## 6. Billing Engine (driven by config)

- **Proration** (on upgrade/downgrade/switch): follows `subscriptions.proration.mode`:
  1. `charge_difference` — charge the difference immediately, no credit.
  2. `credit_new_invoice` — credit the unused portion of the current cycle, invoice the difference (default for Wave 4 slice 2).
  3. `apply_at_renewal` — defer the plan change to the next cycle boundary (same mechanism as downgrade deferral).
  Credit = `plan_price × unused_days / cycle_days` in integer cents; `cycle_days` from `subscriptions.cycle_days[frequency]` (monthly→30, annual→365).
- **Renewal/expiry lifecycle** (`subscriptions.grace_period_days`, `subscriptions.renewal.reminders`): active→grace at `ends_at`, grace→suspended at `grace_until = ends_at + grace_period_days`; reminders fire at the configured lead days (notifications module). Avoids suspended when a deferred plan change (downgrade or `apply_at_renewal` upgrade) applies at the boundary.
- **Suspension behaviour** (`subscriptions.suspension.behaviour`): `keep_listings` (listings stay live, new publishes blocked — Wave 4 default), `hide_listings` (owned `available` properties hidden from the public marketplace), `suspend_premium` (premium features off, basic stays), `full_suspend` (account access restricted). Marketplace/listing queries read the owner's current subscription state.
- **Numbering** (`numbering.*`): every financial document number (`invoice_no`, `receipt_no`, `payment`, `statement`, `subscription`) derives prefix + padding + start + optional financial-year reset from config. Sequences are generated in the little billing/settlement layer so old documents are never renumbered.
- **Invoices/statements/late fees**: shape and timing per `invoices.*`, `late_fees.*`, `statements` (frequency: monthly/on-demand; opening/closing balance, outstanding, payments, credits, refunds, adjustments).

## 7. Approval Engine (general, not per-module)

One approval matrix (config `payments.approval.thresholds` + per-domain rules), not bespoke logic in every controller. Examples: SUBSCRIPTION_APPROVAL, PAYMENT_APPROVAL, OWNER_APPROVAL, PROPERTY_APPROVAL, REFUND_APPROVAL, VERIFICATION_APPROVAL. Admin configures who approves, how many approvals, and at what amount it escalates (e.g. `< $100` staff, `≥ $1000` senior admin). Routes records automatically; the Admin Dashboard shows a Pending Actions queue.

## 8. Business Rules Engine

Rules live as data (JSON in config `rules.*`) describing `IF … THEN …` automation so staff don't run the system by hand:

```
IF subscription expires      THEN start grace period
IF grace period ends         THEN suspend (per behaviour) premium features
IF payment approved          THEN mark invoice paid AND generate receipt AND activate subscription
IF landlord reaches limit    THEN prevent additional active listings
IF featured listing expires  THEN remove featured status (featured window cron)
IF invoice becomes overdue   THEN mark overdue AND send notification
```

Wave 4 ships the subscription/late-fee/featured rules as scheduled commands driven by config values; the full rules catalogue (module 24 completion, Wave 6) reads exactly the same engine.

## 9. Admin Dashboard readouts (driven by the engine)

Pending Actions (owner registrations, property verifications, POPs, payment approvals, refund requests, subscription activations); Financial (today's revenue, monthly revenue, outstanding, overdue, active subscriptions); Platform (landlords, tenants, properties, active listings). All read from ledgers + current configuration.

## 10. Acceptance Criteria

- AC-01 Changing a price/grace/proration/suspension value in the Configuration Centre changes engine behaviour immediately (cached `ConfigurationService`), while historical invoices keep their original values.
- AC-02 Every configuration change writes an audit row (old, new, by, at, reason, approval) — no silent changes.
- AC-03 High/critical changes do not apply until approved.
- AC-04 Billing/subscription behaviour never reads hard-coded commercial constants (grep — no magic numbers for grace/proration/cycle/numbering).
- AC-05 The over-limit, proration, renewal-reminder and suspension-behaviour strings and timings come from configuration, not templates/code.

## 11. Implementation slices (see Module 23 Wave 4)

> **Wave 4 slice 1 — DONE (2026-09-09).** `system_configurations` + `configuration_audits` (`2026_09_09_000017`), `ConfigurationService` (cached/typed/audited), `ConfigSeeder` (20 billing defaults), admin Configuration Centre (`Admin/Configuration/Index.jsx`), Feature Catalogue + per-plan grants (`subscription_plan_features`), and the billing engines conformed to config reads. 13 tests in `ConfigurationTest`; tracking in `docs/modules/23-implementation-plan.md` §1.5. Next: Wave 4 slice 2 (Plans/subscribe config conformance).

> **Wave 4 slice 2 — DONE (2026-09-09).** The subscription engine is fully config-driven: `Subscription::GRACE_DAYS` deleted; `upgrade()` honours `subscriptions.proration.mode` (`charge_difference` / `credit_new_invoice` / `apply_at_renewal` deferred via `pending_upgrade_to`, settled by `applyCycleEnd()`); old invoices remain immutable under config changes. `ConfigurationTest` 14 cases; see §1.5. Next: Wave 4 slice 3 (Rent & Payments core, M9).

> **Wave 4 slice 3 — DONE (2026-09-09).** Rent invoicing is fully config-driven: new defaults `numbering.rent_invoice.prefix` (RNT), `invoices.timing` (billing_date default / immediate), `invoices.reminder_lead_days` (3), `invoices.overdue_days` (0). The `invoices` group auto-appears in the admin Configuration Centre (grouped by `group_name`; no frontend change). `RentService` reads every timing/reminder/numbering rule through `ConfigurationService` — zero commercial constants in code. Re-seeding is idempotent (`ConfigSeeder` is key-based `updateOrCreate`); rent invoices carry their own prefix so they never collide with subscription receipts. See §1.5. Next: Wave 4 slice 4 (Payment, M9).

> **Wave 4 slice 4 — DONE (2026-09-09).** Payments are fully config-driven: `payments.methods` (cash/bank/mobile/online, enabled + details), `payments.pop.approval_required` (bank/mobile raise a POP → payment held), `payments.approval.threshold` (500.00 — `$`-amount and bank/mobile POP ⇒ payment held for staff), `numbering.receipt.prefix` (RCT) + `.length` (8) for the unique receipt. `PaymentService` reads every rule through `ConfigurationService` — zero commercial constants in code. See §1.5. Next: Wave 4 slice 5 (Arrears & income, M9).

> **Wave 4 slice 6 — DONE (2026-09-10).** Featured & advertising is fully config-driven: 6 new `featured.*` keys — `enabled` (bool, high risk), `approval_required` (bool — when off, booking self-activates), `max_per_property` (int, 0 disables the slot), `max_active_per_owner` (int, FR-06 spend cap), plus existing `default_price` and `auto_expiry_days` as **real fallbacks** when an `ad_packages` row has no price/duration. `AdvertisingSeeder` rows come from `ConfigurationService::defaults()` so re-seeding stays idempotent (`ConfigSeeder` key-based `updateOrCreate`). `AdPlacementService` reads every rule through `ConfigurationService` — zero commercial constants in code. See §1.5. Wave 4 is complete; Next: Wave 5 slice 1 (Maintenance report & triage, M10).

1. **Configuration engine (M24)** — ✅ DONE (slice 1): migrations (`system_configurations`, `configuration_audits`) + `ConfigurationService` + seed defaults + admin Configuration Centre (billing group first) + audit + risk-approval queue + tests (change→everywhere, audit rows, approval gate, cache invalidation).
2. **Plans/subscribe (M12)** — ✅ DONE (slice 2): `GRACE_DAYS` removed; upgrade honours `subscriptions.proration.mode` (`charge_difference`/`credit_new_invoice`/`apply_at_renewal` deferred via `pending_upgrade_to`); `applyCycleEnd()` settles deferred upgrades + downgrades; old invoices unchanged by config changes.
3. **Rent schedule (M9)** — ✅ DONE (slice 3): `rent_schedules` + `rent_invoices`, `RentService` generation + `rent:process` lifecycle (draft→due→overdue + one-time reminders), `RNT-YYYY-NNNN` numbering from `numbering.rent_invoice.*`; timing/reminders/overdue days read `invoices.*`.
4. **Payment (M9)** — ✅ DONE (slice 4): `payments.*` methods/POP/threshold + `numbering.receipt.*` receipts, read through `ConfigurationService` by `PaymentService`.
5. **Arrears, late fees & statements (M9)** — ✅ DONE (slice 5): `late_fees.*` + statement config; late fees + income aggregation read every rule through `ConfigurationService`.
6. **Featured & advertising (M13)** — ✅ DONE (slice 6): `featured.*` pricing/duration/window/slot/spend-cap config + `ad_packages` catalogue + `ads:expire` placement-window cron; `AdPlacementService` reads every rule through `ConfigurationService`.