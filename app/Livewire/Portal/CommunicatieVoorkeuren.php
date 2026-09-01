<?php

namespace App\Livewire\Portal;

use App\Models\CommunicationPreference;
use App\Models\Person;
use App\Services\Audit\AuditLogger;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * "Mijn communicatievoorkeuren" (§24.3) — een lid schakelt zelf per
 * categorie redactionele mail in/uit. Eén categorie in v1 ('nieuwsbrief'),
 * generiek genoeg voor meer categorieën later.
 */
#[Layout('layouts.app', ['header' => 'Communicatievoorkeuren'])]
class CommunicatieVoorkeuren extends Component
{
    /** @var array<string, string> */
    private const CATEGORIES = [
        'nieuwsbrief' => 'Nieuwsbrief',
    ];

    /** @var array<string, bool> */
    public array $preferences = [];

    public ?string $statusMessage = null;

    public function mount(): void
    {
        $person = $this->requirePerson();

        $stored = CommunicationPreference::query()
            ->where('person_id', $person->id)
            ->pluck('opted_in', 'category');

        foreach (self::CATEGORIES as $key => $label) {
            $this->preferences[$key] = (bool) ($stored[$key] ?? false);
        }
    }

    public function toggle(string $category, AuditLogger $audit): void
    {
        if (! array_key_exists($category, self::CATEGORIES)) {
            abort(422, "Onbekende categorie [{$category}].");
        }

        $person = $this->requirePerson();
        $newValue = ! ($this->preferences[$category] ?? false);
        $this->preferences[$category] = $newValue;

        $preference = CommunicationPreference::query()->updateOrCreate(
            ['person_id' => $person->id, 'category' => $category],
            ['opted_in' => $newValue],
        );

        $audit->log('communication_preference.updated', $preference, after: [
            'category' => $category,
            'opted_in' => $newValue,
        ]);

        $this->statusMessage = 'Je voorkeuren zijn opgeslagen.';
    }

    public function render(): View
    {
        return view('livewire.portal.communicatie-voorkeuren', ['categories' => self::CATEGORIES]);
    }

    private function requirePerson(): Person
    {
        $person = auth()->user()?->person;
        abort_if($person === null, 403, 'Je account is niet gekoppeld aan een persoon.');

        return $person;
    }
}
