<?php

namespace App\Http\Controllers;

use App\Models\ConfigurationAudit;
use App\Models\SubscriptionFeature;
use App\Models\SubscriptionPlan;
use App\Models\SystemConfiguration;
use App\Services\ConfigurationService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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
            'features' => SubscriptionFeature::orderBy('code')->get(),
            'plans' => SubscriptionPlan::with('features')->orderBy('price')->get(),
            'audits' => ConfigurationAudit::with('changedBy:id,name')->latest('id')->take(10)->get(),
        ]);
    }

    /**
     * Apply configuration edits. Every change is audited; high/critical risk
     * changes must be explicitly approved by the acting staff member.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'values' => 'required|array',
            'reason' => 'nullable|string|max:255',
        ]);

        $changes = 0;
        $errors = [];

        foreach ($validated['values'] as $key => $value) {
            $row = SystemConfiguration::where('key', $key)->first();

            if (! $row || ! $row->is_editable) {
                $errors['values.'.$key] = "Configuration key [{$key}] does not exist or is locked.";
                continue;
            }

            try {
                $this->config->set(
                    $key,
                    $value,
                    $request->user()->id,
                    $validated['reason'] ?? null,
                    in_array($row->risk, ['high', 'critical'], true) ? $request->user()->id : null
                );
                $changes++;
            } catch (ValidationException $e) {
                $errors['values.'.$key] = $e->errors();
            }
        }

        if ($errors) {
            return back()->withErrors($errors);
        }

        return redirect()->route('admin.configuration.index')
            ->with('success', "{$changes} configuration value(s) updated.");
    }
}