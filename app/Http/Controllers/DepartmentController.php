<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DepartmentController extends Controller
{
    public function index()
    {
        $departments = Department::with(['branch.company', 'head'])
            ->withCount('employees')
            ->orderBy('name')
            ->paginate(10);

        return Inertia::render('Admin/Departments/Index', [
            'departments' => $departments,
        ]);
    }

    public function create()
    {
        $branches = Branch::with('company')
            ->where('status', 'active')
            ->get();
        $employees = Employee::where('status', 'active')->orderBy('first_name')->get();

        return Inertia::render('Admin/Departments/Create', [
            'branches' => $branches,
            'employees' => $employees,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:departments,code',
            'description' => 'nullable|string',
            'head_id' => 'nullable|exists:employees,id',
            'status' => 'required|in:active,inactive',
        ]);

        Department::create($validated);

        return redirect()->route('admin.departments.index')
            ->with('success', 'Department created successfully.');
    }

    public function show(Department $department)
    {
        $department->load(['branch.company', 'employees']);

        return Inertia::render('Admin/Departments/Show', [
            'department' => $department,
        ]);
    }

    public function edit(Department $department)
    {
        $department->load('head');
        $branches = Branch::with('company')
            ->where('status', 'active')
            ->get();
        $employees = Employee::where('status', 'active')->orderBy('first_name')->get();

        return Inertia::render('Admin/Departments/Edit', [
            'department' => $department,
            'branches' => $branches,
            'employees' => $employees,
        ]);
    }

    public function update(Request $request, Department $department)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:departments,code,'.$department->id,
            'description' => 'nullable|string',
            'head_id' => 'nullable|exists:employees,id',
            'status' => 'required|in:active,inactive',
        ]);

        $department->update($validated);

        return redirect()->route('admin.departments.index')
            ->with('success', 'Department updated successfully.');
    }

    public function destroy(Department $department)
    {
        if ($department->employees()->exists()) {
            return redirect()->back()
                ->with('error', 'Cannot delete department with employees. Please reassign employees first.');
        }

        $department->delete();

        return redirect()->route('admin.departments.index')
            ->with('success', 'Department deleted successfully.');
    }

    public function toggleStatus(Department $department)
    {
        $department->update([
            'status' => $department->status === 'active' ? 'inactive' : 'active',
        ]);

        return redirect()->back()
            ->with('success', 'Department status updated successfully.');
    }
}
