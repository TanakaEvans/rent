# Module 09 - Rent & Payment Management

> Phase: Phase 2 | Primary actors: Tenant (payer), Owner (collector), Admin (payouts & gateway)
> **Config-driven rule**: payment methods, proof-of-payment rules, approval thresholds, invoice/receipt numbering, due/overdue timing, late fees and statement shapes all read from `ConfigurationService` (Module 24). No bank details, POP rules or fee numbers in code.
> **Implementation status (Wave 4 slices 3–5 — DONE):** FR-01 schedule + invoice generation, the FR-03 state machine (draft/due/overdue transitions + one-time due reminders via `rent:process`), FR-02 invoice numbering (slice 3), FR-04 payment recording + receipts + approval queue (slice 4), and **FR-06 arrears + late fees + FR-07 income aggregation (slice 5)**. `rent_schedules` + `rent_invoices` (`2026_09_09_000018`) + `payments` (`2026_09_09_000019`) tables; `RentService` config-injected (timing/reminders/overdue days/numbering); `PaymentService` config-injected with zero commercial constants — tenant-scoped exact-amount idempotent recording, methods/POP/threshold from `payments.*`, staff approval queue (`admin.rent.payments.{index,approve,reject}`) settling into `paid` + unique `RCT-` receipt + `ReceiptIssuedNotification`, plain-text receipt downloads. Routes: `tenant.rent.pay` POST, `tenant.rent.receipt` download. Tenant "My Rent" page (per-invoice PayForm reading live rules, Due/Overdue/Paid/Receipts stats) + Admin "Payment Approvals" queue. Demo: pending cash payment on the live due invoice (`RNT-2026-0011`, $1,000.00 ≥ threshold). **Slice 5:** `rent_invoices.late_fee` DECIMAL(12,2) (`2026_09_09_000026`) + `late_fees.*` config (`enabled`, `type` fixed|percent, `value`, `cap`, `period_days`) → `RentService::accrueLateFees()` deterministic idempotent charge per applied period; `RentInvoiceOverdueNotification` fires once per invoice to tenant + property owner on due→overdue (seeded history runs silent via `runInvoiceLifecycle(false)`); arrear statements `tenantStatement`/`ownerStatement` (per-invoice rows: amount, late fee, days overdue, server-side totals — AC-04) on both rent pages; `FinancialSummaryService` aggregates monthly income (settled `paid_at`), 6-month income trend, outstanding/arrears and occupancy → landlord dashboard KPIs (Rent Due, Monthly Income, Occupancy). FR-05 (deposits) remains a later slice. Full suite 305 passed / 1500 assertions. Tracking: `docs/modules/23-implementation-plan.md` §1.5.

## 1. Purpose

Transforms Dzimba from a marketplace into a true ERP by handling rent invoicing, collection, receipts, arrears and deposits. It is also the platform's trusted-money layer feeding subscription revenue reporting.

## 2. Roles & Responsibilities

| Actor | Actions |
|---|---|
| **Tenant** | See amount due and payment history, pay rent from the portal, download receipts, get due-date reminders. |
| **Owner** | Create rent schedules/charges, track paid/outstanding/in arrears, record deposits, generate income reports, mark manual payments received. |
| **Admin** | Configure payment methods, POP rules and approval thresholds in the Configuration Centre, reconcile platform billing, handle refunds/payouts, manage rent-payment dispute cases. |

## 3. Functional Requirements

- FR-01 Generate a rent schedule automatically from an active lease (cycle from lease `payment_terms`; invoice timing from `invoices.timing` config).
- FR-02 Generate invoices and store receipts with unique receipt numbers from `numbering.*`.
- FR-03 Payment states: `draft`, `due`, `paid`, `overdue`, `cancelled`, `refunded`.
- FR-04 Record manual payments (cash, bank transfer, EcoCash, mobile money) and online gateway payments; each enabled method (bank/mobile/online/manual + POP rules) comes from `payments.*` config.
- FR-05 Deposit tracking: collected, held, returned/forfeited at lease end.
- FR-06 Arrears computation, **late fees from `late_fees.*` config** (type fixed/percent, value, cap, applied period), overdue flags/notices.
- FR-07 Owner monthly income and outstanding-rent figures feed the landlord dashboard.

## 4. Non-Functional Requirements

- NFR-01 Monetary columns `DECIMAL(12,2)`; all calc server-side, never float.
- NFR-02 Idempotent payment creation (no double-charge on retries).
- NFR-03 Gateway credentials stored encrypted, never in logs.
- NFR-04 Full immutable ledger with invoice/receipt numbering from configuration.

## 5. Workflows & Pseudo Sentences

1. **Schedule** - When a lease becomes `active`, the system generates rent invoices for each cycle until the end date; when a cycle is reached, the system sets the invoice status `due`; then the system sends a due reminder at the configured lead days.
2. **Pay** - When the tenant pays online, the system creates a pending transaction; when the gateway confirms, the system marks the invoice `paid` and emails a receipt; when the owner records a manual payment, the system validates it against the configured POP rules and marks the invoice `paid` with the recorded method.
3. **Overdue** - When a due invoice passes its date without payment, the system moves it to `overdue`; when the configured grace period lapses, the system notifies the owner and tenant; when the late-fee config applies, the system adds a documented late charge.
4. **Approve** - When a payment needs approval (threshold from `payments.approval.thresholds`), the approval engine routes it to the configured approver; on approval the system marks the invoice paid, generates the receipt and (for subscriptions) activates the plan.
5. **Month end** - When the month ends, the system aggregates paid amount, outstanding and arrears per owner; then the system refreshes the landlord dashboard totals.
6. **Deposit** - When a lease ends, the system marks the deposit state; when there are no deductions, the system returns the deposit; when there are damages, the system shows an itemised deduction for the tenant to accept or dispute.

## 6. Data Model

| Table | Key Columns |
|---|---|
| `rent_schedules` | id, lease_id FK, start_date, end_date, rent_amount, payment_terms (json) |
| `rent_invoices` | id, property_id FK, tenant_id FK, lease_id FK, schedule_id FK, period_start, period_end, amount, status, invoice_no (unique, config sequence) |
| `payments` | id, invoice_id FK (cascade), paid_by FK (auth_users, cascade), received_by FK (auth_users, nullable, nullOnDelete), amount DECIMAL(12,2), method (cash/bank/mobile/online), reference, pop_path (nullable), paid_at, receipt_no (unique, `RCT-` config sequence), status (pending/settled/rejected/refunded) |
| `deposits` | id, lease_id FK, amount, status (held/returned/forfeited), deductions (json), returned_at |
| `system_configurations` + `configuration_audits` | Module 24: payments.*, invoices.*, late_fees.*, numbering.*, approval thresholds |

Relationships: `leases` hasMany invoices/payments/deposits; `rent_invoices` hasOne `payments` (latest), belongsTo property/tenant/lease/schedule.

## 7. Integrations & Dependencies

- Module 08 (lease → schedule), Module 12 (platform subscriptions share gateway + numbers config), Module 24 (payment/POP/approval/invoice/late-fee config), Module 15 (due/payment notifications), Module 16/17 (income cards), Module 19 (payment disputes).

## 8. Acceptance Criteria

- AC-01 Invoices generate automatically for the full lease term.
- AC-02 No duplicate payments possible for one invoice.
- AC-03 Receipts are downloadable with a unique number from numbering config.
- AC-04 Arrears and income figures in the dashboard match ledger totals.
- AC-05 Payment methods, POP rules, approval thresholds and late-fee numbers are fully configurable; no payment rule lives in code.