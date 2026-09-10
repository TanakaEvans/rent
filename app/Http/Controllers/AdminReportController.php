<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminReportController extends Controller
{
    public function __construct(private readonly ReportService $reports)
    {
    }

    /**
     * The marketplace moderation queue.
     */
    public function index(Request $request): Response
    {
        $items = $this->reports->listForAdmin([
            'status' => $request->get('status'),
            'priority' => $request->get('priority'),
        ]);

        return Inertia::render('Admin/Marketplace/Reports', [
            'reports' => $items->items(),
            'pagination' => [
                'per_page' => $items->perPage(),
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
            ],
            'stats' => $this->reports->stats(),
            'statuses' => \App\Models\Report::STATUSES,
            'filters' => [
                'status' => $request->get('status'),
                'priority' => $request->get('priority'),
            ],
        ]);
    }

    /**
     * Move a report through the moderation state machine.
     */
    public function transition(Request $request, int $id, string $status): RedirectResponse
    {
        $report = \App\Models\Report::findOrFail($id);

        try {
            $this->reports->transition($report, $status, $request->user(), $request->get('note'), (bool) $request->boolean('hide_listing'));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return back()->with('success', 'Report marked '.$status.'.');
    }
}