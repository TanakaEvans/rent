# Module 22 - System Workflow (end-to-end)

> Pseudo-processing "When ... the system ..." style for each major journey. Each line is an executable/verifiable workflow step.

## 22.1 Property Lifecycle (Owner + Admin)

1. When an owner registers, the system creates the account and auto-assigns the Owner role.
2. When the owner creates a listing, the system stores the property as `available` (MVP) or `draft`.
3. When the owner requests verification, the system opens a verification case (Module 14).
4. When the admin approves the case, the system sets `properties.verified = true` and shows the badge.
5. When a tenant applies and the owner approves, the system sets the property to `reserved`.
6. When the lease is signed and both parties confirmed, the system sets the property to `occupied`.
7. When the owner marks a property off-market, the system sets it to `unavailable` and hides it from search.
8. When the owner republishes, the system returns it to `available` (re-verification if required by policy).

## 22.2 Tenant Journey (Marketplace → Tenancy)

1. When the tenant opens `/`, the system renders the marketplace with filter bar and featured-first listings.
2. When the tenant filters, the system issues a GET with the query params and re-renders the grid.
3. When the tenant opens a listing, the system shows gallery, details, badges and CTAs.
4. When the tenant saves the listing, the system inserts into `property_favourites`.
5. When the tenant enquires, the system stores the enquiry and notifies the owner.
6. When the owner replies, the system notifies the tenant with the read↔reply status transitions.
7. When the tenant requests a viewing, the system creates a `viewing_request` on an available slot.
8. When the owner accepts, the system locks the slot, confirms to the tenant and schedules reminders.
9. When the tenant applies after the viewing, the system creates a `rental_application` (status `pending`).
10. When the owner shortlists, the system records `shortlisted`; when approved, the system sets the property `reserved` and auto-rejects others.
11. When the tenant signs the lease and the owner countersigns, the system activates the lease and marks the property `occupied`.
12. When rent becomes due each cycle, the system issues an invoice and reminders.
13. When the tenant pays, the system marks the invoice `paid`, stores the receipt and notifies the owner.
14. When the tenant reports maintenance, the system creates `MR-####` and notifies the owner/contractor.
15. When the lease ends, the system settles the deposit (return or itemised forfeit) and returns the property to `available`.

## 22.3 Owner ERP Loop

1. When the owner opens `/owner`, the system renders KPI cards + properties table from scoped queries.
2. When the owner manages a status change, the system updates the property and refreshes the dashboard counts.
3. When new enquiries/applications/viewings arrive, the system raises in-app + channel notifications.
4. When the owner assigns a contractor to maintenance, the system sets status `assigned` and notifies the contractor.
5. When the owner buys a subscription/featured slot, the system enforces the plan quota and promotion window.
6. When the month ends, the system aggregates income, arrears and occupancy for reports.

## 22.4 Admin Oversight Loop

1. When data changes across modules, the system feeds the admin dashboard queues.
2. When a verification request arrives, the system queues it; when admin decides, the system writes the badge state.
3. When a report/dispute arrives, the system creates a case and logs an SLA timer.
4. When a listing is reported twice and verified, the system pauses the badge pending review.
5. When a subscription fails payment, the system enters grace; when grace lapses, the system suspends extras.
6. When admin changes a system setting, the system clears caches and applies it on the next request.
7. When an admin resets/unlocks/toggles a user, the system records the action with the actor's identity.

## 22.5 Cross-Entity State Transitions

| Trigger | From | To | System action |
|---|---|---|---|
| Owner creates listing | - | available | publish (MVP auto) |
| Approval of one application | available | reserved | auto-reject others |
| Lease fully signed | reserved | occupied | start rent schedule |
| Lease terminated / move-out | occupied | available | settle deposit |
| Owner removes listing | any | unavailable | hide from search |
| Payment received vs due | due/overdue | paid | issue receipt, clear arrears label |
| Maintenance completed + inspected | in_progress | closed | feed expenditure report |
| Report on verified listing | verified | hold | pause badge |
| Review clears hold | hold | verified / revoked | badge restored or removed |

## 22.6 Numeric Rules Worth Coding Once

- Rent invoices: one per billing cycle between lease start/end.
- Arrears: sum of `due/overdue` invoices minus paid for the tenant+property.
- Income (owner): sum of paid invoices in window.
- Quota: active listings gauged against `subscription_plans.listing_limit`.
- Featured: active when `now() between starts_at and ends_at`.
- Occupancy: `occupied / (occupied + available)` per portfolio or platform.