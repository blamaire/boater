<?php

namespace App\Livewire\Admin;

use App\Enums\ReservationStatus;
use App\Models\ObjectCategory;
use App\Models\Reservation;
use App\Services\Reservations\ReservationService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app', ['header' => 'Reserveringen'])]
class ReserveringBeheer extends Component
{
    public ?int $filterCategoryId = null;

    public string $filterStatus = 'all';

    public bool $hideHistory = true;

    public ?string $statusMessage = null;

    public function cancel(int $reservationId, ReservationService $service): void
    {
        $reservation = Reservation::query()->findOrFail($reservationId);
        $service->cancel($reservation, auth()->user()?->person);
        $this->statusMessage = "Reservering #{$reservation->id} ingetrokken.";
    }

    public function render(): View
    {
        $query = Reservation::query()
            ->with(['object.category', 'person', 'requestedBy'])
            ->orderBy('starts_at');

        if ($this->filterCategoryId !== null) {
            $query->whereHas('object', fn ($q) => $q->where('object_category_id', $this->filterCategoryId));
        }
        if ($this->filterStatus !== 'all') {
            $query->where('status', $this->filterStatus);
        }
        if ($this->hideHistory) {
            $query->where('ends_at', '>=', Carbon::now());
        }

        return view('livewire.admin.reservering-beheer', [
            'reservations' => $query->get(),
            'categories' => ObjectCategory::query()->orderBy('sort_order')->orderBy('name')->get(),
            'statuses' => ReservationStatus::cases(),
        ]);
    }
}
