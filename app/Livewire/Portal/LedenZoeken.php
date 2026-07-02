<?php

namespace App\Livewire\Portal;

use App\Models\FieldDefinition;
use App\Models\Person;
use App\Services\Portal\PersonFieldVisibilityResolver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * §21 — Leden zoeken (besloten laag). Toont per resultaat alleen de velden
 * die op basis van FieldDefinition + PersonFieldVisibility zichtbaar zijn.
 * Naam is altijd zichtbaar; minderjarige contactgegevens zijn standaard
 * verborgen (§21.3) en alleen zichtbaar als er een expliciete opt-in staat.
 */
#[Layout('layouts.app')]
class LedenZoeken extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $zoekterm = '';

    public function updatingZoekterm(): void
    {
        $this->resetPage();
    }

    /**
     * @return array<int, string>
     */
    #[Computed(persist: true)]
    public function searchableFieldKeys(): array
    {
        return FieldDefinition::query()
            ->where('is_searchable', true)
            ->pluck('field_key')
            ->map(fn (mixed $v): string => (string) $v)
            ->all();
    }

    /**
     * @return LengthAwarePaginator<int, Person>
     */
    private function resultaten(): LengthAwarePaginator
    {
        $query = Person::query()
            ->with(['memberships.type'])
            ->orderBy('last_name')
            ->orderBy('first_name');

        $term = trim($this->zoekterm);
        if ($term !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';
            $nameFields = ['first_name', 'last_name_prefix', 'last_name'];
            $searchable = array_values(array_intersect(
                $this->searchableFieldKeys(),
                array_merge($nameFields, ['email']),
            ));
            // Naam-velden zijn altijd doorzoekbaar in de standaardzoek.
            $fields = $searchable !== [] ? $searchable : $nameFields;

            $query->where(function (Builder $q) use ($fields, $like): void {
                foreach ($fields as $field) {
                    $q->orWhere($field, 'like', $like);
                }
            });
        }

        return $query->paginate(15);
    }

    public function render(): View
    {
        $resolver = app(PersonFieldVisibilityResolver::class);
        $resultaten = $this->resultaten();

        // Bouw per persoon een lijst van zichtbare velden om in de blade te tonen.
        $visibleFieldsByPerson = [];
        foreach ($resultaten->items() as $person) {
            /** @var Person $person */
            $visibleFieldsByPerson[$person->id] = $resolver->visibleFieldsFor($person);
        }

        return view('livewire.portal.leden-zoeken', [
            'resultaten' => $resultaten,
            'visibleFieldsByPerson' => $visibleFieldsByPerson,
        ]);
    }
}
