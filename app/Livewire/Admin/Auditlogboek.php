<?php

namespace App\Livewire\Admin;

use App\Models\Activity;
use App\Models\AuditEntry;
use App\Models\Page;
use App\Models\Person;
use App\Models\Proposal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Alleen-lezen inzage op de audit trail (§31.1): doorzoekbaar overzicht met
 * filters op persoon, module en periode. Er wordt hier nooit geschreven —
 * AuditEntry is append-only (zie observer/model-guards).
 */
#[Layout('layouts.app', ['header' => 'Auditlogboek'])]
class Auditlogboek extends Component
{
    use WithPagination;

    public ?int $actorPersonId = null;

    /** Module = het deel vóór de eerste punt in `action` (bijv. `role`, `proposal`). */
    public string $module = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $search = '';

    /** Geselecteerde entry voor de detailweergave (diff + ruwe JSON). */
    public ?int $selectedId = null;

    /**
     * Reset de paginering zodra een filter verandert (maar niet bij het
     * openen/sluiten van een detailregel).
     */
    public function updated(string $property): void
    {
        if ($property !== 'selectedId') {
            $this->resetPage();
        }
    }

    public function show(int $id): void
    {
        $this->selectedId = $id;
    }

    /**
     * Bestemmings-URL voor een subject, of null als er (nog) geen detailpagina
     * bestaat voor dat type. De autorisatie wordt op de doelroute zelf afgedwongen.
     */
    public function subjectUrl(?string $type, ?int $id): ?string
    {
        if ($type === null || $id === null) {
            return null;
        }

        return match ($type) {
            Proposal::class => route('admin.proposals.show', $id),
            Person::class => route('admin.person-permissions.index', $id),
            Activity::class => route('activiteit.show', $id),
            Page::class => route('admin.pages.editor', $id),
            default => null,
        };
    }

    public function closeDetail(): void
    {
        $this->selectedId = null;
    }

    public function resetFilters(): void
    {
        $this->reset(['actorPersonId', 'module', 'dateFrom', 'dateTo', 'search']);
        $this->resetPage();
    }

    public function render(): View
    {
        $selected = $this->selectedId !== null
            ? AuditEntry::query()->with('actor')->find($this->selectedId)
            : null;

        return view('livewire.admin.auditlogboek', [
            'entries' => $this->entries(),
            'modules' => $this->moduleOptions(),
            'actors' => $this->actorOptions(),
            'selected' => $selected,
            'diff' => $selected !== null ? $this->fieldDiff($selected->before, $selected->after) : [],
        ]);
    }

    private function entries(): LengthAwarePaginator
    {
        $query = AuditEntry::query()
            ->with('actor')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');

        if ($this->actorPersonId !== null) {
            $query->where('actor_person_id', $this->actorPersonId);
        }

        if ($this->module !== '') {
            $query->where('action', 'like', $this->module.'.%');
        }

        if ($this->dateFrom !== '') {
            $query->whereDate('occurred_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo !== '') {
            $query->whereDate('occurred_at', '<=', $this->dateTo);
        }

        if ($this->search !== '') {
            $needle = '%'.$this->search.'%';
            $query->where(function (Builder $q) use ($needle) {
                $q->where('action', 'like', $needle)
                    ->orWhere('subject_type', 'like', $needle);
            });
        }

        return $query->paginate(50);
    }

    /**
     * Distinct module-prefixen die daadwerkelijk in de log voorkomen, met NL-label.
     *
     * @return array<string, string> prefix => label
     */
    private function moduleOptions(): array
    {
        $prefixes = AuditEntry::query()
            ->select('action')
            ->distinct()
            ->pluck('action')
            ->map(fn (string $action): string => str_contains($action, '.') ? explode('.', $action, 2)[0] : $action)
            ->unique()
            ->sort()
            ->values();

        $options = [];
        foreach ($prefixes as $prefix) {
            $options[$prefix] = $this->moduleLabel($prefix);
        }

        return $options;
    }

    /**
     * Personen die als actor in de log voorkomen (voor het persoon-filter).
     *
     * @return Collection<int, Person>
     */
    private function actorOptions(): Collection
    {
        $ids = AuditEntry::query()
            ->whereNotNull('actor_person_id')
            ->distinct()
            ->pluck('actor_person_id');

        return Person::query()
            ->whereIn('id', $ids)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * Bereken de veld-voor-veld verschillen tussen before en after.
     *
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     * @return array<int, array{key: string, old: string, new: string}>
     */
    private function fieldDiff(?array $before, ?array $after): array
    {
        $before ??= [];
        $after ??= [];

        $rows = [];
        foreach (array_keys($before + $after) as $key) {
            $old = $before[$key] ?? null;
            $new = $after[$key] ?? null;

            if ($old === $new) {
                continue;
            }

            $rows[] = [
                'key' => (string) $key,
                'old' => $this->stringify($old),
                'new' => $this->stringify($new),
            ];
        }

        return $rows;
    }

    private function stringify(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }

    private function moduleLabel(string $prefix): string
    {
        return match ($prefix) {
            'role' => 'Rollen',
            'person' => 'Personen',
            'person_permission' => 'Directe permissies',
            'household' => 'Huishoudens',
            'user' => 'Gebruikers',
            'proposal' => 'Voorstellen',
            'reservation' => 'Reserveringen',
            'reservation_rule' => 'Reserveringsregels',
            'reservable_object' => 'Objecten',
            'object_category' => 'Objectcategorieën',
            'category_responsible' => 'Categorieverantwoordelijken',
            'damage_report' => 'Schademeldingen',
            'activity' => 'Activiteiten',
            'activity_category' => 'Activiteitcategorieën',
            'menu' => 'Menu',
            'environment' => 'Omgevingen',
            'approver_group' => 'Goedkeuringsgroepen',
            'site_settings' => 'Site-instellingen',
            'ice_contact' => 'ICE-contacten',
            'membership' => 'Lidmaatschap',
            'page' => 'Pagina\'s',
            default => ucfirst(str_replace('_', ' ', $prefix)),
        };
    }
}
