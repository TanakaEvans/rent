<?php

namespace App\Services;

use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\RentInvoice;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * Roles whose names are referenced in code (middleware, landing pages,
     * seeders). Renaming them would silently strip access.
     */
    public const SYSTEM_ROLES = ['Superuser', 'Admin', 'Owner', 'Tenant', 'Contractor'];

    /**
     * Roles that grant access to the admin portal.
     */
    public const ADMIN_ROLES = ['Admin', 'Superuser'];

    public const DEACTIVATED_MESSAGE = 'This account has been deactivated. Please contact support.';

    public const LOCKED_MESSAGE = 'Your account has been locked due to too many failed login attempts. Please contact an administrator.';

    /**
     * Public self-service tenant registration (Presentation Release S3).
     * New users always land on the Tenant role with an active account and a
     * password they chose themselves (no forced-change loop). The unique
     * `username` is derived from the email local-part so the public form never
     * asks for it; collisions get a numeric suffix.
     */
    public function registerTenant(array $data): User
    {
        return $this->registerWithRole($data, 'Tenant');
    }

    /**
     * Public owner self-signup — creates an active account on the Owner role so
     * a landlord can list without waiting for an admin. Listings still pass
     * through the existing verification workflow.
     */
    public function registerOwner(array $data): User
    {
        return $this->registerWithRole($data, 'Owner');
    }

    /**
     * Shared self-signup: an active account, username derived from the email,
     * not forced to change the password, on exactly one starting role.
     */
    private function registerWithRole(array $data, string $role): User
    {
        $email = Str::lower($data['email']);

        $user = User::create([
            'name' => $data['name'],
            'email' => $email,
            'username' => $this->uniqueUsername($email),
            'password' => Hash::make($data['password']),
            'status' => 'active',
            'password_changed_at' => now(),
        ]);

        $user->assignRole($role);

        return $user;
    }

    /**
     * The role dashboard a user lands on, or null when no role has one.
     */
    public function landingUrlFor(User $user): ?string
    {
        if ($user->hasAnyRole(self::ADMIN_ROLES)) {
            return route('admin.dashboard');
        }

        if ($user->hasRole('Owner')) {
            return route('owner.dashboard');
        }

        if ($user->hasRole('Tenant')) {
            return route('tenant.dashboard');
        }

        if ($user->hasRole('Contractor')) {
            return route('contractor.maintenance.index');
        }

        return null;
    }

    /**
     * Why the actor may not manage the target account at all, or null.
     * Only a Superuser can change a Superuser account (otherwise an Admin could
     * take one over by resetting its password or email).
     */
    public function manageBlocker(User $actor, User $target): ?string
    {
        if ($target->hasRole('Superuser') && ! $actor->hasRole('Superuser')) {
            return 'Only a Superuser can change a Superuser account.';
        }

        return null;
    }

    /**
     * Only a Superuser may hand out the Superuser role (e.g. on a new account).
     *
     * @param  array<int, int>  $roleIds
     */
    public function guardGrant(User $actor, array $roleIds, string $field): void
    {
        if (! $actor->hasRole('Superuser') && Role::whereIn('id', $roleIds)->where('name', 'Superuser')->exists()) {
            throw ValidationException::withMessages([$field => 'Only a Superuser can grant or remove the Superuser role.']);
        }
    }

    /**
     * Give a brand-new account its roles, recording who assigned them.
     *
     * @param  array<int, int>  $roleIds
     */
    public function grantInitialRoles(User $actor, User $user, array $roleIds): void
    {
        $this->applyRoles($actor, $user, array_map('intval', $roleIds));
    }

    /**
     * Admin edit of a system user: profile, status, optional password and roles.
     *
     * @param  array{name: string, email: string, username: string, status: string, password?: ?string, roles?: array<int, int>}  $data
     */
    public function updateAccount(User $actor, User $user, array $data): void
    {
        $this->throwIf($this->manageBlocker($actor, $user), 'roles');

        if ($actor->is($user) && $data['status'] !== 'active') {
            throw ValidationException::withMessages(['status' => 'You cannot deactivate your own account.']);
        }

        $roleIds = array_map('intval', $data['roles'] ?? []);
        $this->guardRoleChange($actor, $user, $roleIds, 'roles');

        DB::transaction(function () use ($actor, $user, $data, $roleIds) {
            $attributes = [
                'name' => $data['name'],
                'email' => Str::lower($data['email']),
                'username' => $data['username'],
                'status' => $data['status'],
            ];

            if (! empty($data['password'])) {
                $attributes['password'] = Hash::make($data['password']);
                // A password set by someone else must be replaced by its owner.
                $attributes['password_changed_at'] = $actor->is($user) ? now() : null;
            }

            $user->update($attributes);
            $this->applyRoles($actor, $user, $roleIds);
        });
    }

    /**
     * Add roles to many users at once (existing roles are kept).
     *
     * @param  array<int, int>  $userIds
     * @param  array<int, int>  $roleIds
     */
    public function bulkAssignRoles(User $actor, array $userIds, array $roleIds): int
    {
        return $this->bulkChangeRoles($actor, $userIds, fn (Collection $current) => $current->merge($roleIds)->unique()->values()->all());
    }

    /**
     * Remove roles from many users at once.
     *
     * @param  array<int, int>  $userIds
     * @param  array<int, int>  $roleIds
     */
    public function bulkRemoveRoles(User $actor, array $userIds, array $roleIds): int
    {
        return $this->bulkChangeRoles($actor, $userIds, fn (Collection $current) => $current->diff($roleIds)->values()->all());
    }

    /**
     * Flip a user between active and inactive. Returns an error message when
     * the change is not allowed, otherwise null.
     */
    public function toggleStatus(User $actor, User $user): ?string
    {
        if ($blocker = $this->manageBlocker($actor, $user)) {
            return $blocker;
        }

        if ($actor->is($user) && $user->status === 'active') {
            return 'You cannot deactivate your own account.';
        }

        $user->update(['status' => $user->status === 'active' ? 'inactive' : 'active']);

        return null;
    }

    /**
     * Why a user may not be deleted, or null. Accounts with tenancy or money
     * history are never hard-deleted (the foreign keys cascade); they are
     * deactivated instead.
     */
    public function deletionBlocker(User $actor, User $user): ?string
    {
        if ($actor->is($user)) {
            return 'You cannot delete your own account.';
        }

        if ($blocker = $this->manageBlocker($actor, $user)) {
            return $blocker;
        }

        $hasHistory = Property::where('owner_id', $user->id)->exists()
            || Lease::where('tenant_id', $user->id)->exists()
            || RentInvoice::where('tenant_id', $user->id)->exists()
            || Payment::where('paid_by', $user->id)->exists()
            || Subscription::where('owner_id', $user->id)->exists();

        if ($hasHistory) {
            return 'This user has properties, leases, invoices, payments or subscriptions on record and cannot be deleted. Deactivate the account instead.';
        }

        return null;
    }

    /**
     * Reset a user's password to a temporary one they must change at next
     * sign-in; also clears any lockout. Employees get their lowercase surname,
     * everyone else a random password. Returns the temporary password.
     */
    public function resetPassword(User $user): string
    {
        $surname = $user->employee?->last_name;
        $temporary = $surname ? Str::lower($surname) : Str::password(14, symbols: false);

        $user->update([
            'password' => Hash::make($temporary),
            'password_changed_at' => null,
            'password_expires_at' => now()->addMonths(5),
            'failed_login_attempts' => 0,
            'locked_at' => null,
        ]);

        return $temporary;
    }

    /**
     * @param  array<int, int>  $userIds
     * @param  callable(Collection<int, int>): array<int, int>  $finalRoles
     */
    private function bulkChangeRoles(User $actor, array $userIds, callable $finalRoles): int
    {
        $users = User::with('roles')->whereIn('id', $userIds)->get();

        $plan = $users->map(function (User $user) use ($actor, $finalRoles) {
            $this->throwIf($this->manageBlocker($actor, $user), 'user_ids');
            $roleIds = array_map('intval', $finalRoles($user->roles->pluck('id')));
            $this->guardRoleChange($actor, $user, $roleIds, 'role_ids');

            return [$user, $roleIds];
        });

        DB::transaction(function () use ($actor, $plan) {
            foreach ($plan as [$user, $roleIds]) {
                $this->applyRoles($actor, $user, $roleIds);
            }
        });

        return $users->count();
    }

    /**
     * Only a Superuser may grant or remove the Superuser role, and nobody may
     * strip their own admin access.
     *
     * @param  array<int, int>  $finalRoleIds
     */
    private function guardRoleChange(User $actor, User $user, array $finalRoleIds, string $field): void
    {
        $current = $user->roles()->pluck('name');
        $final = Role::whereIn('id', $finalRoleIds)->pluck('name');
        $changed = $current->diff($final)->merge($final->diff($current));

        if ($changed->contains('Superuser') && ! $actor->hasRole('Superuser')) {
            throw ValidationException::withMessages([$field => 'Only a Superuser can grant or remove the Superuser role.']);
        }

        if ($actor->is($user) && $current->intersect(self::ADMIN_ROLES)->diff($final)->isNotEmpty()) {
            throw ValidationException::withMessages([$field => 'You cannot remove your own Admin or Superuser role.']);
        }
    }

    /**
     * Make the user's roles exactly $roleIds, recording who assigned new ones
     * and leaving existing assignments (and their history) untouched.
     *
     * @param  array<int, int>  $roleIds
     */
    private function applyRoles(User $actor, User $user, array $roleIds): void
    {
        $current = $user->roles()->pluck('auth_roles.id')->all();
        $removed = array_diff($current, $roleIds);
        $added = array_diff($roleIds, $current);

        if ($removed) {
            $user->roles()->detach($removed);
        }

        if ($added) {
            $user->roles()->attach(array_fill_keys($added, ['assigned_by' => $actor->id]));
        }

        $user->unsetRelation('roles');
    }

    private function throwIf(?string $message, string $field): void
    {
        if ($message !== null) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }

    private function uniqueUsername(string $email): string
    {
        $local = strstr($email, '@', true) ?: 'user';
        $base = preg_replace('/[^a-z0-9._-]+/', '', $local);
        $base = $base === '' || $base === null ? 'user' : Str::limit($base, 60, '');

        $candidate = $base;
        $suffix = 1;
        while (User::where('username', $candidate)->exists()) {
            $candidate = $base.$suffix;
            $suffix++;
        }

        return $candidate;
    }
}
