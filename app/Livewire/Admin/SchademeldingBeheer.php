<?php

namespace App\Livewire\Admin;

use App\Enums\DamageReportStatus;
use App\Models\DamageReport;
use App\Models\ObjectCategory;
use App\Models\Person;
use App\Services\DamageReports\DamageReportService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Beheerlijst voor schademeldingen (§22.2). Toont openstaande meldingen,
 * met filters op status en categorie, en biedt acties: toewijzen aan een
 * behandelaar, status wijzigen, en het object weer op beschikbaar zetten
 * als het door de melder op buiten_gebruik is gezet.
 */
#[Layout('layouts.app', ['header' => 'Schademeldingen'])]
class SchademeldingBeheer extends Component
{
    public ?int $filterCategoryId = null;

    public string $filterStatus = 'open';

    public ?int $expandedReportId = null;

    public ?string $statusMessage = null;

    public ?int $assigneeInput = null;

    public string $resolutionInput = '';

    public function toggle(int $reportId): void
    {
        $this->expandedReportId = $this->expandedReportId === $reportId ? null : $reportId;
        $this->resolutionInput = '';
        $this->assigneeInput = null;
    }

    public function assign(int $reportId, DamageReportService $service): void
    {
        $actor = auth()->user()?->person;
        if ($actor === null) {
            return;
        }
        $report = DamageReport::query()->findOrFail($reportId);
        $assignee = $this->assigneeInput !== null ? Person::query()->find($this->assigneeInput) : null;
        $service->assign($report, $assignee, $actor);
        $this->statusMessage = "Melding #{$report->id} toegewezen.";
    }

    public function markResolved(int $reportId, DamageReportService $service): void
    {
        $this->applyTransition($reportId, DamageReportStatus::Resolved, $service);
    }

    public function markRejected(int $reportId, DamageReportService $service): void
    {
        $this->applyTransition($reportId, DamageReportStatus::Rejected, $service);
    }

    public function markInProgress(int $reportId, DamageReportService $service): void
    {
        $this->applyTransition($reportId, DamageReportStatus::InProgress, $service);
    }

    public function restoreObject(int $reportId, DamageReportService $service): void
    {
        $actor = auth()->user()?->person;
        if ($actor === null) {
            return;
        }
        $report = DamageReport::query()->with('object')->findOrFail($reportId);
        $service->restoreObject($report->object, $actor, $report);
        $this->statusMessage = "Object [{$report->object->name}] weer op beschikbaar gezet.";
    }

    private function applyTransition(int $reportId, DamageReportStatus $to, DamageReportService $service): void
    {
        $actor = auth()->user()?->person;
        if ($actor === null) {
            return;
        }
        $report = DamageReport::query()->findOrFail($reportId);
        $service->changeStatus($report, $to, $actor, $this->resolutionInput !== '' ? $this->resolutionInput : null);
        $this->statusMessage = "Melding #{$report->id}: {$to->label()}.";
        $this->resolutionInput = '';
    }

    public function render(): View
    {
        $query = DamageReport::query()
            ->with(['object.category', 'reportedBy', 'assignedTo', 'photos'])
            ->orderByDesc('reported_at');

        if ($this->filterCategoryId !== null) {
            $query->whereHas('object', fn ($q) => $q->where('object_category_id', $this->filterCategoryId));
        }
        if ($this->filterStatus === 'open') {
            $query->whereIn('status', [DamageReportStatus::Reported->value, DamageReportStatus::InProgress->value]);
        } elseif ($this->filterStatus !== 'all') {
            $query->where('status', $this->filterStatus);
        }

        return view('livewire.admin.schademelding-beheer', [
            'reports' => $query->get(),
            'categories' => ObjectCategory::query()->orderBy('name')->get(),
            'statuses' => DamageReportStatus::cases(),
            'personsForAssignment' => Person::query()->orderBy('last_name')->orderBy('first_name')->limit(200)->get(),
        ]);
    }
}
