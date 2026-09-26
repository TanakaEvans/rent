<?php

namespace App\Http\Controllers;

use App\Models\ConfigurationAudit;
use App\Models\SubscriptionFeature;
use App\Models\SubscriptionPlan;
use App\Models\SystemConfiguration;
use App\Services\ConfigurationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminConfigController extends Controller
{
    public function __construct(private readonly ConfigurationService $config)
    {
    }

    /**
     * The Configuration Centre: commercial rules grouped for editors, the
     * Feature Catalogue with per-plan grants, and a recent-change audit trail.
     */
    public function index()
    {
        $rows = SystemConfiguration::orderBy('group_name')->orderBy('key')->get();

        return Inertia::render('Admin/Configuration/Index', [
            'groups' => $rows->groupBy('group_name'),
            'options' => ConfigurationService::optionMap(),
            'features' => SubscriptionFeature::orderBy('code')->get(),
            'plans' => SubscriptionPlan::with('features')->orderBy('price')->get(),
            'audits' => ConfigurationAudit::with('changedBy:id,name')->latest('id')->take(10)->get(),
        ]);
    }

    /**
     * Apply configuration edits. Only values that actually change are
     * written and audited; high/critical risk changes record the acting
     * staff member as the approver.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'values' => 'required|array',
            'reason' => 'nullable|string|max:255',
        ]);

        $result = $this->config->applyChanges($validated['values'], $request->user()->id, $validated['reason'] ?? null);

        if ($result['errors']) {
            return back()->withErrors($result['errors'])
                ->with('success', $result['changed'] > 0 ? "{$result['changed']} configuration value(s) updated; fix the highlighted values." : null);
        }

        return redirect()->route('admin.configuration.index')
            ->with('success', $result['changed'] > 0
                ? "{$result['changed']} configuration value(s) updated."
                : 'No changes to save.');
    }
}