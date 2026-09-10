# Module 18 - Admin / System Management Module

> Phase: MVP (existing system admin preserved) | Implemented | Primary actor: Admin

## 1. Purpose

The platform's administration cockpit: user management, access control, organisation structure, system configuration and oversight of every other module's admin surfaces.

## 2. Roles & Responsibilities

| Actor | Actions |
|---|---|
| **Admin** | Manage users and roles, assign routes to roles, manage company/branches/departments/sections/employees, configure system settings, verify owners/properties (Module 14), resolve disputes (Module 19), manage subscriptions/ads (12/13), review reports (17). |
| **Owner/Tenant** | No direct access; their data and behaviour feed this console. |

## 3. Functional Requirements

- FR-01 **Users**: create, edit, deactivate, reset passwords, unlock (`/auth/users`).
- FR-02 **Auth management**: reset, toggle status, unlock per user (`/auth/management/{user}/...`).
- FR-03 **Roles**: define roles and descriptions; assign to users via pivot.
- FR-04 **Route-level access**: map system routes to roles (`system_modules`, `system_routes`, `role_routes`), bulk-assign routes to roles.
- FR-05 **Organisation**: companies, branches, departments, sections, employees (existing ZENT structure preserved).
- FR-06 **Settings**: key/value system configuration (`system_settings`) — legacy key/value store; superseded for commercial rules by the **Configuration Centre** (Module 24).
- FR-06b **Configuration Centre (Module 24, Wave 4)**: every commercial/billing rule lives in `system_configurations` (grouped namespaced keys, typed values, risk tier, audit trail) and is edited by staff through the admin ⚙️ Configuration Centre — no developer involvement for pricing/policy changes; high/critical risk changes require approval (`configuration_audits.approved_by`).
- FR-07 **Oversight**: queues for verification, disputes, reported listings, subscription issues.
- FR-08 Audit log of admin actions.

## 4. Non-Functional Requirements

- NFR-01 The `admin` middleware group protects admin + `/auth/*` routes (403 otherwise).
- NFR-02 Route-role auditability: `role_routes` unique (role_id, system_route_id).
- NFR-03 Sensitive actions (reset/unlock/toggle) logged with `assigned_by` / auditor identity.

## 5. Workflows & Pseudo Sentences

1. **Create user** - When admin creates a user, the system stores credentials in `auth_users`; when admin assigns roles, the system writes `auth_user_roles`; then the system notifies the user with next-step instructions.
2. **Reset password** - When admin resets a password, the system clears `password_changed_at`; when the user next logs in, the system forces a change (Module 01).
3. **Bulk role assignment** - When admin selects multiple users and a role, the system inserts pivots respecting unique constraints; when duplicates are attempted, the system skips and reports them.
4. **Route-role mapping** - When admin toggles a route for a role, the system updates `role_routes`; when a user with that role hits a route, the middleware consults the mapping and allows/denies.
5. **Config change** - When admin changes a setting (legacy `system_settings`), the system writes the row and clears the relevant cache. When staff change a **commercial rule** (grace days, proration mode, suspension behaviour, numbering, over-limit copy, payment/POP/approval, late fees, featured pricing) through the Configuration Centre, the system validates and typecasts the value, writes `system_configurations`, records a **`configuration_audits`** row (old/new/changed_by/reason, effective_from) and invalidates the ConfigurationService cache; when the risk tier is high/critical and the workflow requires approval, the system holds the change until an approver is recorded. Changes apply to the next engine read; existing invoices/cycles are never rewritten.
6. **Oversight queue** - When new verification/dispute/feature requests arrive, the system increments the admin dashboard queue; when admin resolves one, the system logs the decision and decreases the queue.

## 6. Data Model (existing admin tables)

| Table | Key Columns | Purpose |
|---|---|---|
| `auth_users` | id, name, email, username, status | users (shared with Module 01) |
| `auth_roles` | id, name, description | roles |
| `auth_user_roles` | user_id, role_id, assigned_by | role assignment |
| `auth_login_logs` | user_id, ip_address, user_agent, login_at, logout_at | audit |
| `companies` | id, name, contact fields | org root |
| `branches` | id, company_id FK, name, location | org branch |
| `departments` | id, company_id/branch_id FK, name, head | org department |
| `sections` | id, name, department_id FK, head_id FK, status | org section |
| `employees` | id, user_id FK, branch_id, department_id, employee_number, name fields, job_title, status | staff |
| `system_modules` | id, name, prefix (unique), icon, order, status | module registry |
| `system_routes` | id, system_module_id FK, name (unique), uri, status | route registry |
| `role_routes` | id, role_id FK (auth_roles), system_route_id FK | route-role mapping |
| `system_settings` | id, key (unique), value, group | legacy config |
| `system_configurations` | id, group_name, key (unique), type, value, label, description, risk, is_editable, status | **Configuration Centre keys (Module 24)** |
| `configuration_audits` | id, configuration_id FK, key, old_value, new_value, changed_by FK, reason, approved_by FK, effective_from, created_at | **every config change history** |

## 7. Integrations & Dependencies

- Module 01 (users/roles), 02 (property moderation), 14 (verification queue), 12/13 (plan/ad admin), 17 (platform analytics), 19 (disputes), 20 (document registry).

## 8. Acceptance Criteria

AC-01 Tenant/Owner get 403 on `/auth/*` and `/admin/*`.
AC-02 Role-route changes take effect on next request.
AC-03 Admin actions are audited.
AC-04 System settings apply without redeploy.
AC-05 Commercial rules are configuration: a staff member changes grace/price/proration/suspension/numbering in the Configuration Centre and the billing engine uses it with zero code change (Module 24 AC-05).