<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MarketplaceReportController extends Controller
{
    public function __construct(private readonly ReportService $reports)
    {
    }

    /**
     * Public "Report listing" endpoint. Guests may report a listing (the
     * required login prompt is covered by the frontend).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'subject_type' => 'required|in:property,user',
            'subject_id' => 'required|integer',
            'category' => 'required|string|max:100',
            'description' => 'required|string|max:2000',
            'priority' => 'sometimes|in:low,medium,high',
        ]);

        try {
            $this->reports->create($request->user(), $validated);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return back()->with('success', 'Report submitted. Our team will look into it.');
    }

    /**
     * The reporter's own reports.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Tenant/Reports', [
            'reports' => $this->reports->listFor($request->user())->get(),
        ]);
    }
}