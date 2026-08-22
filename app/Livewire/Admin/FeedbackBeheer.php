<?php

namespace App\Livewire\Admin;

use App\Enums\FeedbackCategory;
use App\Enums\FeedbackStatus;
use App\Models\Feedback;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Inbox van via `FeedbackWidget` ingediende terugkoppeling. Puur een
 * logboek met een statusveld — geen goedkeuringsworkflow via de
 * ProposalEngine. Permissie: `feedback.manage`.
 */
#[Layout('layouts.app', ['header' => 'Terugkoppeling'])]
class FeedbackBeheer extends Component
{
    use WithPagination;

    #[Url]
    public string $filterStatus = '';

    #[Url]
    public string $filterCategory = '';

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedFilterCategory(): void
    {
        $this->resetPage();
    }

    public function updateStatus(int $feedbackId, string $status, AuditLogger $audit): void
    {
        $newStatus = FeedbackStatus::tryFrom($status);
        if ($newStatus === null) {
            return;
        }

        $feedback = Feedback::query()->findOrFail($feedbackId);
        $before = ['status' => $feedback->status->value];
        $feedback->update(['status' => $newStatus]);
        $audit->log('feedback.status_updated', $feedback, before: $before, after: ['status' => $newStatus->value]);
    }

    public function render(): View
    {
        return view('livewire.admin.feedback-beheer', [
            'items' => $this->items(),
            'statuses' => FeedbackStatus::cases(),
            'categories' => FeedbackCategory::cases(),
        ]);
    }

    private function items(): LengthAwarePaginator
    {
        return Feedback::query()
            ->with(['person', 'page'])
            ->when($this->filterStatus !== '', fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterCategory !== '', fn ($q) => $q->where('category', $this->filterCategory))
            ->orderByDesc('created_at')
            ->paginate(25);
    }
}
