<?php

namespace App\Livewire\Portal;

use App\Models\Person;
use App\Services\Portal\PersonFieldVisibilityResolver;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * §21.1 — Detail-profielkaart in de besloten ledengids.
 * Alleen-lezen; respecteert dezelfde zichtbaarheids-vlaggen als
 * LedenZoeken (via PersonFieldVisibilityResolver).
 */
#[Layout('layouts.app')]
class LedenProfiel extends Component
{
    public int $personId;

    public function mount(Person $person): void
    {
        $this->personId = $person->id;
    }

    public function render(): View
    {
        $person = Person::query()
            ->with(['memberships.type'])
            ->findOrFail($this->personId);

        $resolver = app(PersonFieldVisibilityResolver::class);
        $visibleFields = $resolver->visibleFieldsFor($person);

        return view('livewire.portal.leden-profiel', [
            'person' => $person,
            'visibleFields' => $visibleFields,
        ]);
    }
}
