<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CompanyController extends Controller
{
    public function index()
    {
        $company = Company::with('head')->first();
        $employees = Employee::where('status', 'active')->orderBy('first_name')->get();

        return Inertia::render('Admin/Company/Index', [
            'company' => $company,
            'employees' => $employees,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'trading_name' => 'nullable|string|max:255',
            'registration_number' => 'nullable|string|max:100',
            'tax_number' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'website' => 'nullable|url|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'currency' => 'nullable|string|max:10',
            'head_id' => 'nullable|exists:employees,id',
        ]);

        $company = Company::first();

        if ($company) {
            $company->update($validated);
            $message = 'Company details updated successfully.';
        } else {
            Company::create($validated);
            $message = 'Company details created successfully.';
        }

        return redirect()->back()->with('success', $message);
    }

    public function uploadLogo(Request $request)
    {
        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        $company = Company::first();

        if (! $company) {
            return redirect()->back()->with('error', 'Please create company details first.');
        }

        if ($request->hasFile('logo')) {
            // Delete old logo if exists
            if ($company->logo && file_exists(public_path($company->logo))) {
                unlink(public_path($company->logo));
            }

            $logo = $request->file('logo');
            $logoName = 'company_logo_'.time().'.'.$logo->getClientOriginalExtension();
            $logo->move(public_path('uploads/company'), $logoName);

            $company->update(['logo' => 'uploads/company/'.$logoName]);
        }

        return redirect()->back()->with('success', 'Company logo updated successfully.');
    }
}
