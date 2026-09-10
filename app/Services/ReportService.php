<?php

namespace App\Services;

use App\Models\Property;
use App\Models\PropertyHistory;
use App\Models\Report;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Marketplace reports and their admin moderation queue (Module 19 /
 * Marketplace §35). Status flows through an explicit state machine kept on
 * the Report model; resolving can optionally take the offending listing down.
 */
class ReportService
{
    public function __construct(private readonly ConfigurationService $config)
    {
    }

    /**
     * File a report against a property or user. Guests may report too
     * (reporter_id stays null); think "Report listing" on the detail page.
     */
    public function create(?User $reporter, array $data): Report
    {
        $categories = (array) $this->config->get('marketplace.report_categories', ['suspicious_listing', 'incorrect_information', 'duplicate', 'wrong_price', 'fraud_concern', 'already_rented', 'inappropriate_content']);

        $subjectType = $data['subject_type'] ?? 'property';
        if ($subjectType !== 'property' && $subjectType !== 'user') {
            throw ValidationException::withMessages(['subject_type' => 'Invalid report subject.']);
        }

        $category = $data['category'] ?? '';
        if (! in_array($category, $categories, true)) {
            throw ValidationException::withMessages(['category' => 'Please choose a valid report category.']);
        }

        if ($subjectType === 'property') {
            $property = Property::find($data['subject_id'] ?? null);
            if (! $property) {
                throw new NotFoundHttpException('Property not found.');
            }
        }

        if (trim((string) ($data['description'] ?? '')) === '') {
            throw ValidationException::withMessages(['description' => 'Please describe the issue so we can investigate.']);
        }

        $priority = $data['priority'] ?? 'medium';

        return Report::create([
            'reporter_id' => $reporter?->id,
            'subject_type' => $subjectType,
            'subject_id' => (int) $data['subject_id'],
            'category' => $category,
            'description' => trim($data['description']),
            'status' => 'open',
            'priority' => in_array($priority, ['low', 'medium', 'high'], true) ? $priority : 'medium',
        ]);
    }

    /**
     * The reporter's own reports, newest first.
     */
    public function listFor(User $user)
    {
        return Report::query()->where('reporter_id', $user->id)->latest();
    }

    /**
     * The admin moderation queue with optional status/priority filters.
     *
     * @param array<string, mixed> $filters
     */
    public function listForAdmin(array $filters = [])
    {
        $query = Report::query()->with(['reporter:id,name,email']);

        if (isset($filters['status']) && array_key_exists($filters['status'], Report::STATUSES)) {
            $query->where('status', $filters['status']);
        }
        if ($filters['priority'] ?? null && in_array($filters['priority'], ['low', 'medium', 'high'], true)) {
            $query->where('priority', $filters['priority']);
        }

        return $query->latest()->paginate(15)->withQueryString();
    }

    public function stats(): array
    {
        return [
            'open' => Report::whereIn('status', ['open', 'under_review'])->count(),
            'escalated' => Report::where('status', 'escalated')->count(),
            'resolved' => Report::where('status', 'resolved')->count(),
            'dismissed' => Report::where('status', 'dismissed')->count(),
        ];
    }

    /**
     * Move a report through its state machine. Resolving/dismissing records
     * the moderator and resolution; `hideListing` takes the property down.
     */
    public function transition(Report $report, string $newStatus, User $admin, ?string $note = null, bool $hideListing = false): Report
    {
        if (! array_key_exists($newStatus, Report::STATUSES)) {
            throw ValidationException::withMessages(['status' => 'Invalid report status.']);
        }

        if (! $report->canTransitionTo($newStatus)) {
            throw ValidationException::withMessages([
                'status' => "Cannot move the report from {$report->status} to {$newStatus}.",
            ]);
        }

        $isClosed = in_array($newStatus, ['resolved', 'dismissed'], true);

        $report->update([
            'status' => $newStatus,
            'resolved_by' => $isClosed ? $admin->id : null,
            'resolved_at' => $isClosed ? now() : null,
            'resolution_note' => $isClosed ? (trim((string) $note) ?: null) : null,
        ]);

        if ($hideListing && $newStatus === 'resolved' && $report->subject_type === 'property') {
            $property = Property::find($report->subject_id);
            if ($property && $property->status === 'available') {
                $property->update(['status' => 'unavailable']);
                PropertyHistory::create([
                    'property_id' => $property->id,
                    'from_status' => 'available',
                    'to_status' => 'unavailable',
                    'changed_by' => $admin->id,
                    'note' => 'Listing removed after resolved report',
                ]);
            }
        }

        return $report->fresh(['reporter:id,name,email']);
    }
}