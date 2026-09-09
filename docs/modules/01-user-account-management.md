# Module 01 - User & Account Management

> Phase: MVP | Implemented (core) | Primary actors: Admin, Owner, Tenant

## 1. Purpose

Manage every person who uses Dzimba: tenants, property owners, staff and administrators. It provides registration, authentication, roles, session security, password hygiene and account lifecycle controls.

## 2. Roles & Responsibilities

| Actor | Actions |
|---|---|
| **Admin** | Create users, assign roles, reset passwords, unlock accounts, activate/deactivate accounts, review login history, enforce password policy, audit activity. |
| **Owner** | Register, complete profile, update contact details/phone/address, change password, manage own sessions, submit verification documents. |
| **Tenant** | Register, complete profile, preferences and contact details, change password, manage own sessions, opt in/out of notifications. |

## 3. Functional Requirements

- FR-01 Register with name, email OR username, and password.
- FR-02 Authenticate by **email or username** plus password (bcrypt).
- FR-03 Enforce password policy: minimum length, forced change on first login, expiry.
- FR-04 Lockout after **7 failed attempts**; manual unlock by admin; reset attempts on success.
- FR-05 Assign one or many roles via the pivot `auth_user_roles`.
- FR-06 Admin can create/edit/deactivate/reactivate users (`/auth/users`).
- FR-07 Admin can reset a user's password, toggle status and unlock (`/auth/management`).
- FR-08 Record every login and logout in `auth_login_logs`.
- FR-09 Role-aware redirect after login: Admin/Superuser → `/admin/dashboard`, Owner → `/owner`, Tenant → `/tenant`.

## 4. Non-Functional Requirements

- NFR-01 Password hashes havehed with bcrypt via Laravel `hashed` cast.
- NFR-02 CSRF protection on all state-changing forms.
- NFR-03 403 for users attempting a role they do not hold (`EnsureRole`, `AdminMiddleware`).
- NFR-04 Users with no role are forced to the generic `/dashboard` and cannot access role areas.
- NFR-05 Sessions regenerate on login/logout (`session->regenerate()`).

## 5. Workflows & Pseudo Sentences

1. **Registration** - When a visitor submits the registration form, the system validates the details; then the system creates the `auth_users` record; after that the system assigns default role(s) via `auth_user_roles`; finally the system sends a welcome notification.
2. **Login** - When a user submits credentials, the system matches `email` or `username`; if the match fails, the system increments `failed_login_attempts`; when the counter reaches 7, the system sets `locked_at`; if the match succeeds, the system resets the counter, sets `password_changed_at` check, and redirects by role via `landingFor()`.
3. **First login / password change** - When a user logs in with `password_changed_at` null, the system redirects them to `password.change`; when the user submits a new password, the system updates `password_changed_at` and clears `password_expires_at`.
4. **Admin reset** - When an admin resets a user's password, the system sets a temporary password; then the system clears `password_changed_at` so the user must change it on next login.
5. **Deactivation** - When an admin toggles `status` to inactive, the system blocks further logins; then the system invalidates active sessions.
6. **Unlock** - When a locked user returns, the system shows a lock notice; when an admin unlocks the user, the system clears `locked_at` and resets `failed_login_attempts`.

## 6. Data Model

| Table | Key Columns | Notes |
|---|---|---|
| `auth_users` | id, name, email, username (unique), password, status (active/inactive), email_verified_at, remember_token | Login table. |
| `auth_users` (extras) | password_changed_at, password_expires_at, failed_login_attempts, locked_at | Expiry/lockout migration columns. |
| `auth_roles` | id, name (unique), description | Roles incl. Superuser, Admin, Owner, Tenant, Staff. |
| `auth_user_roles` | user_id, role_id, assigned_by | Pivot; unique (user_id, role_id). |
| `auth_login_logs` | user_id, ip_address, user_agent, login_at, logout_at | Session/audit trail. |

Relationships: `auth_users` hasMany `auth_user_roles` → belongsToMany `auth_roles`; a user can hold several roles concurrently (e.g. a tenant who also owns a property).

## 7. Integrations & Dependencies

- `EnsurePasswordIsChanged` middleware (allowlist: password.change/update, logout, login).
- `EnsureHasRole` middleware (role-less users get 403 on protected groups).
- Feeds every other module via the authenticated user and role checks.

## 8. Access Map

| Route | Guard |
|---|---|
| /login | guest |
| /logout | auth |
| /dashboard | auth (any role-holder) |
| /admin/dashboard | auth + Admin/Superuser |
| /owner | auth + Owner |
| /tenant | auth + Tenant |
| /auth/users, /auth/management/{user}/* | auth + Admin/Superuser |

## 9. Acceptance Criteria

AC-01 A tenant/owner restricted path returns HTTP 403 for the wrong role.
AC-02 A locked account (7 failures) cannot log in until unlocked.
AC-03 A first-time user is forced to change their password before proceeding.
AC-04 Login redirect targets the correct role dashboard.