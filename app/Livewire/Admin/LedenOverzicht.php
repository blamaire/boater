<?php

namespace App\Livewire\Admin;

use App\Enums\MembershipStatus;
use App\Models\MembershipType;
use App\Models\Person;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * §19.2 — Overzicht van leden voor de ledenadministratie.
 * Zoek op naam/e-mail; filter op lidmaatschapsvorm + status.
 */
class LedenOverzicht extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $zoekterm = '';

    #[Url(as: 'type', except: '')]
    public string $membershipTypeFilter = '';

    #[Url(as: 'status', except: '')]
    public string $statusFilter = '';

    public function updatingZoekterm(): void
    {
        $this->resetPage();
    }

    public function updatingMembershipTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        return view('livewire.admin.leden-overzicht', [
            'personen' => $this->personen(),
            'membershipTypes' => MembershipType::query()->orderBy('sort_order')->get(),
            'statussen' => MembershipStatus::cases(),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, Person>
     */
    private function personen(): LengthAwarePaginator
    {
        $query = Person::query()
            ->with(['memberships.type'])
            ->orderBy('last_name')
            ->orderBy('first_name');

        $term = trim($this->zoekterm);
        if ($term !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';
            $query->where(function (Builder $q) use ($like): void {
                $q->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('email', 'like', $like);
            });
        }

        if ($this->membershipTypeFilter !== '') {
            $typeId = (int) $this->membershipTypeFilter;
            $query->whereHas('memberships', function (Builder $q) use ($typeId): void {
                $q->where('membership_type_id', $typeId);
            });
        }

        if ($this->statusFilter !== '') {
            $status = $this->statusFilter;
            $query->whereHas('memberships', function (Builder $q) use ($status): void {
                $q->where('status', $status);
            });
        }

        return $query->paginate(25);
    }
}
