<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BranchController extends Controller
{
    public function index()
    {
        $branches = Branch::with(['company', 'head'])
            ->withCount(['employees', 'departments'])
            ->orderBy('name')
            ->paginate(10);

        return Inertia::render('Admin/Branches/Index', [
            'branches' => $branches,
        ]);
    }

    public function create()
    {
        $companies = Company::where('status', 'active')->get();
        $employees = Employee::where('status', 'active')->orderBy('first_name')->get();

        return Inertia::render('Admin/Branches/Create', [
            'companies' => $companies,
            'employees' => $employees,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:branches,code',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'head_id' => 'nullable|exists:employees,id',
            'is_main_branch' => 'boolean',
            'status' => 'required|in:active,inactive',
        ]);

        // If setting as main branch, unset other main branches
        if ($validated['is_main_branch'] ?? false) {
            Branch::where('company_id', $validated['company_id'])
                ->update(['is_main_branch' => false]);
        }

        Branch::create($validated);

        return redirect()->route('admin.branches.index')
            ->with('success', 'Branch created successfully.');
    }

    public function show(Branch $branch)
    {
        $branch->load(['company', 'departments', 'employees']);

        return Inertia::render('Admin/Branches/Show', [
            'branch' => $branch,
        ]);
    }

    public function edit(Branch $branch)
    {
        $branch->load('head');
        $companies = Company::where('status', 'active')->get();
        $employees = Employee::where('status', 'active')->orderBy('first_name')->get();

        return Inertia::render('Admin/Branches/Edit', [
            'branch' => $branch,
            'companies' => $companies,
            'employees' => $employees,
        ]);
    }

    public function update(Request $request, Branch $branch)
    {
        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:branches,code,'.$branch->id,
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'head_id' => 'nullable|exists:employees,id',
            'is_main_branch' => 'boolean',
            'status' => 'required|in:active,inactive',
        ]);

        // If setting as main branch, unset other main branches
        if ($validated['is_main_branch'] ?? false) {
            Branch::where('company_id', $validated['company_id'])
                ->where('id', '!=', $branch->id)
                ->update(['is_main_branch' => false]);
        }

        $branch->update($validated);

        return redirect()->route('admin.branches.index')
            ->with('success', 'Branch updated successfully.');
    }

    public function destroy(Branch $branch)
    {
        if ($branch->employees()->exists()) {
            return redirect()->back()
                ->with('error', 'Cannot delete branch with employees. Please reassign employees first.');
        }

        $branch->delete();

        return redirect()->route('admin.branches.index')
            ->with('success', 'Branch deleted successfully.');
    }

    public function toggleStatus(Branch $branch)
    {
        $branch->update([
            'status' => $branch->status === 'active' ? 'inactive' : 'active',
        ]);

        return redirect()->back()
            ->with('success', 'Branch status updated successfully.');
    }
}
