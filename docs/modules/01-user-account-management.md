# Module 01 - User & Account Management

> Phase: MVP | Implemented (core) | Primary actors: Admin, Owner, Tenant

> **Implementation status — profile photos & account settings (Sep 2026):** every signed-in user has an **Account settings** page (`account.profile`) with an avatar upload (`account.avatar.store`/`destroy`). `auth_users.avatar_path` backs it; `User` appends `avatar_url` (served from the public disk) and `initials`, so the shared `Avatar` component shows the photo when set and the user's initials otherwise. The avatar appears on the header profile button and beside chat messages across signed-in areas. Uploads are image-only and capped at 4 MB; a user can only change their own avatar. Verified in `tests/Feature/Fixes/ProfilePhotoTest.php`.

## 1. Purpose

Manage every person who uses ZimRent: tenants, property owners, staff and administrators. It provides registration, authentication, roles, session security, password hygiene and account lifecycle controls.

## 2. Roles & Responsibilities

| Actor | Actions |
|---|---|
| **Admin** | Create users, assign roles, reset passwords, unlock accounts, activate/deactivate accounts, review login history, enforce password policy, audit activity. |
| **Owner** | Register, complete profile, update contact details/phone/address, change password, manage own sessions, submit verification documents. |
| **Tenant** | Register, complete profile, preferences and contact details, change password, manage own sessions, opt in/out of notifications. |

## 3. Functional Requirements

- FR-01 Register with name, email OR username, and password. **Public self-service signup (S3)** creates a tenant: email lowercased, username auto-derived from the email local-part (sanitised `[a-z0-9._-]`, ≤60, numeric suffix on collision), `password_changed_at` stamped, **`Tenant` role assigned by default** (`assigned_by` null — no unassigned signups; staff/moderators stay invitation-only). Guests use `/register`; authenticated users bounce to their own dashboard.
- FR-02 Authenticate by **email or username** plus password (bcrypt).
- FR-03 Enforce password policy: minimum length, forced change on first login, expiry.
- FR-04 Lockout after **7 failed attempts**; manual unlock by admin; reset attempts on success.
- FR-05 Assign one or many roles via the pivot `auth_user_roles`.
- FR-06 Admin can create/edit/deactivate/reactivate users (`/auth/users`).
- FR-07 Admin can reset a user's password, toggle status and unlock (`/auth/management`).
- FR-08 Record every login and logout in `auth_login_logs`.
- FR-09 Role-aware redirect after login: Admin/Superuser → `/admin/dashboard`, Owner → `/owner`, Tenant → `/tenant`.
- FR-10 **Tenant profile + KYC (S4/S5)**: a tenant maintains contact/employment/income/preferred-contact/about and uploads identity evidence (national ID card, driving licence) to the private disk; one scan per type, re-upload replaces and resets to `pending`; every change is audited.
- FR-11 **Identity review (S5)**: Admin/Superuser approve, reject (with note) or revoke evidence through an explicit state machine (`pending → approved|rejected`, `approved → rejected`, rejected terminal). Decisions drive the **derived** `badge_tier` (full KYC→gold, basic→silver, any evidence→bronze) — there is no manual badge toggle. The tenant is notified in-app on every decision.

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
| `tenant_profiles` (S4) | user_id (unique FK), phone, city, employment_status, salary_band, preferred_contact, about | Tenant's self-managed profile; created on first save, all fields optional. |
| `identity_documents` (S4, review S5) | user_id, type (national_id/driving_licence), file_path, original_name, mime, size, status (pending/approved/rejected) | KYC evidence; private disk (`storage/app/private/kyc/…`, never public); unique (user_id, type) — re-upload replaces the file; **S5** adds the admin review state machine (pending→approved|rejected, approved→rejected) and derives `auth_users.badge_tier` from the approved types. |
| `identity_document_audits` (S4/S5) | user_id, document_id (nullable, nullOnDelete), action (uploaded/replaced/removed/approved/rejected/revoked), actor_id, details | Immutable trail; survives document deletion; action widened to VARCHAR(20) in S5. |

Relationships: `auth_users` hasMany `auth_user_roles` → belongsToMany `auth_roles`; a user can hold several roles concurrently (e.g. a tenant who also owns a property).

## 7. Integrations & Dependencies

- `EnsurePasswordIsChanged` middleware (allowlist: password.change/update, logout, login).
- `EnsureHasRole` middleware (role-less users get 403 on protected groups).
- Feeds every other module via the authenticated user and role checks.

## 8. Access Map

| Route | Guard |
|---|---|
| /login | guest |
| /register | guest |
| /logout | auth |
| /dashboard | auth (any role-holder) |
| /admin/dashboard | auth + Admin/Superuser |
| /owner | auth + Owner |
| /tenant | auth + Tenant |
| /tenant/profile (+ documents upload/delete/download, S4) | auth + Tenant (owner of the profile/KYC rows; Admin/Superuser may review others' — S5) |
| /admin/kyc (+ approve/reject/revoke/download, S5) | auth + Admin/Superuser |
| /auth/users, /auth/management/{user}/* | auth + Admin/Superuser |

## 9. Acceptance Criteria

AC-01 A tenant/owner restricted path returns HTTP 403 for the wrong role.
AC-02 A locked account (7 failures) cannot log in until unlocked.
AC-03 A first-time user is forced to change their password before proceeding.
AC-04 Login redirect targets the correct role dashboard.

## 10. Authentication flows (design + status)

The signed-out surface is: **Register**, **Login**, **Forgot / Reset password**, and (post-login, when required) **Forced password change**. All are Inertia pages behind guest/auth middleware; passwords always hash with `Rules\Password::defaults()` strength.

### 10.1 Account creation

| Path | Who | How |
|---|---|---|
| Public **tenant** signup | Anyone | `POST /register` with `role=tenant` (default) → `AuthService::registerTenant` → `Tenant` role, auto-login, land on tenant dashboard. |
| Public **owner** signup **(Sep 2026)** | Anyone listing property | `POST /register` with `role=owner` → `AuthService::registerOwner` → `Owner` role, auto-login, land on owner dashboard. The marketplace **"List your property"** CTAs deep-link to `register?as=owner`, which preselects the owner tab. |
| Admin-created staff/owner | Admin/Superuser | User-management screen (`auth.management`) assigns any role; the account starts with `password_changed_at = null` so it is forced through the change screen on first sign-in. |

Registration rules (both roles): `name` required, `email` unique + lowercased, `password` confirmed + default strength, **terms accepted**, username auto-derived from the email, `status = active`, `password_changed_at = now()` (self-signup users are not forced to change). Owner self-signup does **not** auto-verify the owner — listings still pass through the existing verification/badge workflow (Module 14); signup only creates the account and role.

### 10.2 Login, lockout, forced change (built)

- `POST /login` — email **or** username + password. Each failure increments `failed_login_attempts`; at **7** the account locks (`AuthService::LOCKED_MESSAGE`, "contact an administrator"). Success resets the counter.
- `password/change` (`EnsurePasswordIsChanged`) forces accounts with a null `password_changed_at` to set a new password before using the app. Demo users keep it set to avoid the loop.
- Admin recovery: `auth.management.reset` issues a one-time temporary password (clears lockout, forces change) and `auth.management.unlock` clears a lockout.

### 10.3 Self-service password reset **(Sep 2026)** — Laravel password broker

Standard, best-practice broker flow (`config/auth.php` `passwords.users`, `password_reset_tokens` table, 60-min token expiry, 60-sec throttle):

1. `GET /forgot-password` — guest form asking for the account email.
2. `POST /forgot-password` — `Password::sendResetLink()`. The response is **explicit** (product decision): a match returns "A password reset link is on its way", an unregistered email returns a validation error "That email isn't linked to any ZimRent account", and a repeat within the window returns a throttle notice. This favours clear feedback over user-enumeration resistance. Throttled by the broker.
3. `GET /reset-password/{token}` — guest form (token + email prefilled) to choose a new password.
4. `POST /reset-password` — `Password::reset()`; on success sets the new hash, **`password_changed_at = now()`** (so the user is not then forced through the change screen), clears `failed_login_attempts` (a forgotten password often means a locked account), fires the `PasswordReset` event, and redirects to login with a success flash.

The reset link is delivered by Laravel's `ResetPassword` notification over the configured mailer. **Operational note:** `MAIL_MAILER` must be a real transport (SMTP) in production; the default `log` transport writes the link to the log instead of sending it.

AC-05 Owner self-signup creates an `Owner` (not a tenant) and lands on the owner dashboard.
AC-06 A reset request for an unknown email returns an explicit "not linked to any account" error and sends no mail.
AC-07 A valid reset token sets the new password, clears any lockout, and stamps `password_changed_at` so no forced change follows.
AC-08 An invalid/expired token is rejected and the password is unchanged.