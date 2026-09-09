<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $query = Employee::with(['branch', 'department', 'user.roles']);

        // Search
        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('employee_number', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by branch
        if ($request->branch_id) {
            $query->where('branch_id', $request->branch_id);
        }

        // Filter by department
        if ($request->department_id) {
            $query->where('department_id', $request->department_id);
        }

        // Filter by status
        if ($request->status) {
            $query->where('status', $request->status);
        }

        $employees = $query->orderBy('first_name')->paginate(15)->withQueryString();

        $branches = Branch::where('status', 'active')->get();
        $departments = Department::where('status', 'active')->get();

        return Inertia::render('Admin/Employees/Index', [
            'employees' => $employees,
            'branches' => $branches,
            'departments' => $departments,
            'filters' => $request->only(['search', 'branch_id', 'department_id', 'status']),
        ]);
    }

    public function create()
    {
        $branches = Branch::with('departments')->where('status', 'active')->get();
        $departments = Department::where('status', 'active')->get();
        $roles = Role::orderBy('name')->get();

        return Inertia::render('Admin/Employees/Create', [
            'branches' => $branches,
            'departments' => $departments,
            'roles' => $roles,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_number' => 'required|string|max:50|unique:employees,employee_number',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'gender' => 'nullable|in:male,female,other',
            'date_of_birth' => 'nullable|date',
            'national_id' => 'nullable|string|max:50',
            'email' => [
                'nullable',
                'email',
                'max:255',
                function ($attribute, $value, $fail) use ($request) {
                    if ($request->boolean('create_user_account')) {
                        if (empty($value)) {
                            $fail('An email address is required when creating a system user account.');
                        }
                        if (User::where('email', $value)->exists()) {
                            $fail('This email address is already associated with an existing system user account.');
                        }
                    }
                },
            ],
            'phone' => 'nullable|string|max:50',
            'alt_phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'branch_id' => 'nullable|exists:branches,id',
            'department_id' => 'nullable|exists:departments,id',
            'job_title' => 'nullable|string|max:255',
            'hire_date' => 'nullable|date',
            'employment_type' => 'required|in:full_time,part_time,contract,intern',
            'salary' => 'nullable|numeric|min:0',
            'bank_name' => 'nullable|string|max:255',
            'bank_account' => 'nullable|string|max:100',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:50',
            'status' => 'required|in:active,inactive,terminated,suspended',
            // User account fields
            'create_user_account' => 'boolean',
            'role_ids' => 'nullable|array',
            'role_ids.*' => 'exists:auth_roles,id',
        ]);

        DB::beginTransaction();

        try {
            $userId = null;

            // Create user account if requested
            if ($request->create_user_account) {
                // Auto-generate credentials
                $username = $validated['employee_number'];
                $password = strtolower($validated['last_name']);

                // check if username already exists
                if (User::where('username', $username)->exists()) {
                    throw new \Exception("Username '{$username}' already exists. Please ensure employee number is unique.");
                }

                $user = User::create([
                    'name' => $validated['first_name'].' '.$validated['last_name'],
                    'email' => $validated['email'],
                    'username' => $username,
                    'password' => Hash::make($password),
                    'status' => 'active',
                    'password_changed_at' => null, // null means they haven't changed it yet
                    'password_expires_at' => now()->addMonths(5),
                ]);

                $userId = $user->id;

                // Assign roles
                if (! empty($request->role_ids)) {
                    $user->roles()->sync($request->role_ids);
                }
            }

            // Create employee
            Employee::create([
                'user_id' => $userId,
                'branch_id' => $validated['branch_id'],
                'department_id' => $validated['department_id'],
                'employee_number' => $validated['employee_number'],
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'national_id' => $validated['national_id'] ?? null,
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'alt_phone' => $validated['alt_phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'city' => $validated['city'] ?? null,
                'job_title' => $validated['job_title'] ?? null,
                'hire_date' => $validated['hire_date'] ?? null,
                'employment_type' => $validated['employment_type'],
                'salary' => $validated['salary'] ?? null,
                'bank_name' => $validated['bank_name'] ?? null,
                'bank_account' => $validated['bank_account'] ?? null,
                'emergency_contact_name' => $validated['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $validated['emergency_contact_phone'] ?? null,
                'status' => $validated['status'],
            ]);

            DB::commit();

            return redirect()->route('admin.employees.index')
                ->with('success', 'Employee created successfully.');

        } catch (QueryException $e) {
            DB::rollBack();
            if ($e->errorInfo[1] == 1062) {
                // Determine which key caused the duplicate
                if (str_contains($e->getMessage(), 'auth_users_email_unique')) {
                    return redirect()->back()
                        ->with('error', 'The email address is already in use by another user account.')
                        ->withInput();
                }
                if (str_contains($e->getMessage(), 'employees_employee_number_unique')) {
                    return redirect()->back()
                        ->with('error', 'The employee number is already in use.')
                        ->withInput();
                }
            }

            return redirect()->back()
                ->with('error', 'Database error: '.$e->getMessage())
                ->withInput();
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()
                ->with('error', 'Error creating employee: '.$e->getMessage())
                ->withInput();
        }
    }

    public function show(Employee $employee)
    {
        $employee->load(['branch', 'department', 'user.roles']);

        return Inertia::render('Admin/Employees/Show', [
            'employee' => $employee,
        ]);
    }

    public function edit(Employee $employee)
    {
        $employee->load(['user.roles']);
        $branches = Branch::with('departments')->where('status', 'active')->get();
        $departments = Department::where('status', 'active')->get();
        $roles = Role::orderBy('name')->get();

        return Inertia::render('Admin/Employees/Edit', [
            'employee' => $employee,
            'branches' => $branches,
            'departments' => $departments,
            'roles' => $roles,
        ]);
    }

    public function update(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'employee_number' => 'required|string|max:50|unique:employees,employee_number,'.$employee->id,
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'gender' => 'nullable|in:male,female,other',
            'date_of_birth' => 'nullable|date',
            'national_id' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'alt_phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'branch_id' => 'nullable|exists:branches,id',
            'department_id' => 'nullable|exists:departments,id',
            'job_title' => 'nullable|string|max:255',
            'hire_date' => 'nullable|date',
            'employment_type' => 'required|in:full_time,part_time,contract,intern',
            'salary' => 'nullable|numeric|min:0',
            'bank_name' => 'nullable|string|max:255',
            'bank_account' => 'nullable|string|max:100',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:50',
            'status' => 'required|in:active,inactive,terminated,suspended',
        ]);

        $employee->update($validated);

        // Update user name if linked
        if ($employee->user) {
            $employee->user->update([
                'name' => $validated['first_name'].' '.$validated['last_name'],
                'email' => $validated['email'],
            ]);
        }

        return redirect()->route('admin.employees.index')
            ->with('success', 'Employee updated successfully.');
    }

    public function destroy(Employee $employee)
    {
        DB::beginTransaction();

        try {
            // If employee has a linked user, decide what to do
            if ($employee->user) {
                // Option: Deactivate user instead of deleting
                $employee->user->update(['status' => 'inactive']);
            }

            $employee->delete();

            DB::commit();

            return redirect()->route('admin.employees.index')
                ->with('success', 'Employee deleted successfully.');

        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()
                ->with('error', 'Error deleting employee: '.$e->getMessage());
        }
    }

    public function toggleStatus(Employee $employee)
    {
        $newStatus = $employee->status === 'active' ? 'inactive' : 'active';
        $employee->update(['status' => $newStatus]);

        // Update linked user status as well
        if ($employee->user) {
            $employee->user->update(['status' => $newStatus]);
        }

        return redirect()->back()
            ->with('success', 'Employee status updated successfully.');
    }

    public function createUserAccount(Employee $employee)
    {
        if ($employee->user_id) {
            return redirect()->back()
                ->with('error', 'Employee already has a user account.');
        }

        $roles = Role::orderBy('name')->get();

        return Inertia::render('Admin/Employees/CreateUserAccount', [
            'employee' => $employee,
            'roles' => $roles,
        ]);
    }

    public function storeUserAccount(Request $request, Employee $employee)
    {
        if ($employee->user_id) {
            return redirect()->back()
                ->with('error', 'Employee already has a user account.');
        }

        $validated = $request->validate([
            'username' => 'required|string|max:255|unique:auth_users,username',
            'password' => 'required|string|min:8',
            'role_ids' => 'nullable|array',
            'role_ids.*' => 'exists:auth_roles,id',
        ]);

        DB::beginTransaction();

        try {
            $user = User::create([
                'name' => $employee->full_name,
                'email' => $employee->email,
                'username' => $validated['username'],
                'password' => Hash::make($validated['password']),
                'status' => 'active',
            ]);

            // Assign roles
            if (! empty($request->role_ids)) {
                $user->roles()->sync($request->role_ids);
            }

            $employee->update(['user_id' => $user->id]);

            DB::commit();

            return redirect()->route('admin.employees.show', $employee)
                ->with('success', 'User account created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()
                ->with('error', 'Error creating user account: '.$e->getMessage())
                ->withInput();
        }
    }
}
