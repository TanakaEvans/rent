<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Section;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SectionController extends Controller
{
    public function index()
    {
        $sections = Section::with(['department', 'head'])->latest()->paginate(10);
        $departments = Department::where('status', 'active')->get();
        // Optimize: select only necessary fields for dropdown
        $employees = Employee::select('id', 'first_name', 'last_name', 'employee_number')->get();

        return Inertia::render('Admin/Sections/Index', [
            'sections' => $sections,
            'departments' => $departments,
            'employees' => $employees,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'department_id' => 'required|exists:departments,id',
            'head_id' => 'nullable|exists:employees,id',
            'description' => 'nullable|string',
        ]);

        Section::create($request->all());

        return back()->with('success', 'Section created successfully.');
    }

    public function update(Request $request, Section $section)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'department_id' => 'required|exists:departments,id',
            'head_id' => 'nullable|exists:employees,id',
            'description' => 'nullable|string',
        ]);

        $section->update($request->all());

        return back()->with('success', 'Section updated successfully.');
    }

    public function destroy(Section $section)
    {
        $section->delete();

        return back()->with('success', 'Section deleted successfully.');
    }
}
