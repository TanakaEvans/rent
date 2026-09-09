# Module 09 - Rent & Payment Management

> Phase: Phase 2 | Primary actors: Tenant (payer), Owner (collector), Admin (payouts & gateway)

## 1. Purpose

Transforms Dzimba from a marketplace into a true ERP by handling rent invoicing, collection, receipts, arrears and deposits. It is also the platform's trusted-money layer feeding subscription revenue reporting.

## 2. Roles & Responsibilities

| Actor | Actions |
|---|---|
| **Tenant** | See amount due and payment history, pay rent from the portal, download receipts, get due-date reminders. |
| **Owner** | Create rent schedules/charges, track paid/outstanding/in arrears, record deposits, generate income reports, mark manual payments received. |
| **Admin** | Configure payment gateway, reconcile platform billing, handle refunds/payouts, manage rent-payment dispute cases. |

## 3. Functional Requirements

- FR-01 Generate a rent schedule automatically from an active lease (monthly cycles).
- FR-02 Generate invoices and store receipts with unique receipt numbers.
- FR-03 Payment states: `draft`, `due`, `paid`, `overdue`, `cancelled`, `refunded`.
- FR-04 Record manual payments (cash, bank transfer, EcoCash, mobile money) and online gateway payments.
- FR-05 Deposit tracking: collected, held, returned/forfeited at lease end.
- FR-06 Arrears computation and late-payment flags/notices.
- FR-07 Owner monthly income and outstanding-rent figures feed the landlord dashboard.

## 4. Non-Functional Requirements

- NFR-01 Monetary columns `DECIMAL(12,2)`; all calc server-side, never float.
- NFR-02 Idempotent payment creation (no double-charge on retries).
- NFR-03 Gateway credentials stored encrypted, never in logs.
- NFR-04 Full immutable ledger with invoice/receipt numbering.

## 5. Workflows & Pseudo Sentences

1. **Schedule** - When a lease becomes `active`, the system generates rent invoices for each cycle until the end date; when a cycle is reached, the system sets the invoice status `due`; then the system sends a due reminder (day -7, day -1).
2. **Pay** - When the tenant pays online, the system creates a pending transaction; when the gateway confirms, the system marks the invoice `paid` and emails a receipt; when the owner records a manual payment, the system marks the invoice `paid` with the recorded method.
3. **Overdue** - When a due invoice passes its date without payment, the system moves it to `overdue`; when a grace period lapses, the system notifies the owner and tenant; when fees apply, the system adds a documented late charge.
4. **Month end** - When the month ends, the system aggregates paid amount, outstanding and arrears per owner; then the system refreshes the landlord dashboard totals.
5. **Deposit** - When a lease ends, the system marks the deposit state; when there are no deductions, the system returns the deposit; when there are damages, the system shows an itemised deduction for the tenant to accept or dispute.

## 6. Data Model

| Table | Key Columns |
|---|---|
| `rent_schedules` | id, lease_id FK, start_date, end_date, rent_amount, payment_terms (json) |
| `rent_invoices` | id, property_id FK, tenant_id FK, lease_id FK, schedule_id FK, period_start, period_end, amount, status, invoice_no (unique) |
| `payments` | id, invoice_id FK, paid_by FK, received_by FK (nullable), amount, method (cash/bank/mobile/gateway), reference, paid_at, receipt_no |
| `deposits` | id, lease_id FK, amount, status (held/returned/forfeited), deductions (json), returned_at |

Relationships: `leases` hasMany invoices/payments/deposits; `rent_invoices` hasOne `payments` (latest), belongsTo property/tenant/lease/schedule.

## 7. Integrations & Dependencies

- Module 08 (lease → schedule), Module 12 (platform subscriptions share gateway), Module 15 (due/payment notifications), Module 16/17 (income cards), Module 19 (payment disputes).

## 8. Acceptance Criteria

AC-01 Invoices generate automatically for the full lease term.
AC-02 No duplicate payments possible for one invoice.
AC-03 Receipts are downloadable with a unique number.
AC-04 Arrears and income figures in the dashboard match ledger totals.