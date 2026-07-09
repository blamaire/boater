<?php

namespace App\Livewire\Portal;

use App\Enums\DamageSeverity;
use App\Enums\PageVisibility;
use App\Models\DamageReport;
use App\Models\MediaAsset;
use App\Models\ReservableObject;
use App\Models\Reservation;
use App\Services\DamageReports\DamageReportService;
use App\Services\Media\MediaUploadService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use RuntimeException;

/**
 * Portaalscherm "Schade melden" (§22.1). Toont eigen meldingen en een
 * formulier om een nieuwe melding in te dienen. Bij "niet bruikbaar"
 * gaat het object direct op buiten_gebruik (§22.4, omkeerbaar door
 * een schadebehandelaar).
 */
#[Layout('layouts.app', ['header' => 'Schade melden'])]
class SchadeMelden extends Component
{
    use WithFileUploads;

    public ?int $selectedObjectId = null;

    public ?int $reservationId = null;

    public string $description = '';

    public string $severity = 'middel';

    public bool $reporterMarkedUnusable = false;

    /**
     * @var array<int, TemporaryUploadedFile>
     */
    public array $photos = [];

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public function submit(DamageReportService $service, MediaUploadService $uploader): void
    {
        $this->errorMessage = null;

        $this->validate([
            'selectedObjectId' => ['required', 'integer', 'exists:reservable_objects,id'],
            'reservationId' => ['nullable', 'integer', 'exists:reservations,id'],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
            'severity' => ['required', 'string', 'in:laag,middel,hoog'],
            'photos.*' => ['image', 'max:10240'],
        ]);

        $user = auth()->user();
        if ($user === null || $user->person === null) {
            $this->errorMessage = 'Log opnieuw in om een schade te melden.';

            return;
        }

        $object = ReservableObject::query()->findOrFail($this->selectedObjectId);
        $reservation = $this->reservationId !== null
            ? Reservation::query()->find($this->reservationId)
            : null;

        // Foto-uploads landen als MediaAsset met context='damage_report'
        // zodat ze niet in de mediabibliotheek verschijnen (§22, koppeling
        // Media). Elke upload is een `TemporaryUploadedFile` → `UploadedFile`.
        $assets = collect($this->photos)->map(fn ($upload): MediaAsset => $uploader->store(
            file: $upload,
            uploadedBy: $user->person,
            visibility: PageVisibility::Restricted,
            context: MediaAsset::CONTEXT_DAMAGE_REPORT,
        ));

        try {
            $service->submit(
                object: $object,
                reporter: $user->person,
                description: $this->description,
                severity: DamageSeverity::from($this->severity),
                reporterMarkedUnusable: $this->reporterMarkedUnusable,
                photos: $assets,
                reservation: $reservation,
            );
        } catch (RuntimeException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->reset(['selectedObjectId', 'reservationId', 'description', 'reporterMarkedUnusable', 'photos']);
        $this->severity = 'middel';
        $this->statusMessage = 'Bedankt — je melding is doorgegeven aan de verantwoordelijke voor deze categorie.';
    }

    public function render(): View
    {
        $user = auth()->user();
        $ownPerson = $user?->person;

        return view('livewire.portal.schade-melden', [
            'objects' => ReservableObject::query()->with('category')->orderBy('name')->get(),
            'ownReservations' => $ownPerson === null
                ? collect()
                : Reservation::query()
                    ->with('object')
                    ->where('person_id', $ownPerson->id)
                    ->orderByDesc('starts_at')
                    ->limit(20)
                    ->get(),
            'myReports' => $ownPerson === null
                ? collect()
                : DamageReport::query()
                    ->with(['object'])
                    ->where('reported_by_person_id', $ownPerson->id)
                    ->orderByDesc('reported_at')
                    ->limit(20)
                    ->get(),
            'severities' => DamageSeverity::cases(),
        ]);
    }
}
