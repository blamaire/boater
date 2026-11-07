<?php

namespace App\Services\Search;

use App\Enums\ActivityStatus;
use App\Enums\ActivityVisibility;
use App\Models\Activity;
use App\Models\Page;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Zoekt over pagina's, activiteiten en (voor beheerders) personen, met
 * dezelfde zichtbaarheidsregels als de publieke pagina's en de agenda.
 */
class SearchService
{
    private const MIN_LENGTH = 2;

    private const PAGE_LIMIT = 20;

    private const ACTIVITY_LIMIT = 20;

    private const PERSON_LIMIT = 10;

    private const SNIPPET_LENGTH = 160;

    /** Escape-teken voor LIKE; werkt identiek op MySQL en SQLite. */
    private const LIKE_ESCAPE = '!';

    /**
     * @return list<array{key: string, label: string, results: list<SearchResult>}>
     */
    public function search(string $query, ?User $user): array
    {
        $query = trim($query);
        if (mb_strlen($query) < self::MIN_LENGTH) {
            return [];
        }

        $groups = [
            ['key' => 'paginas', 'label' => "Pagina's", 'results' => $this->searchPages($query, $user)],
            ['key' => 'activiteiten', 'label' => 'Activiteiten', 'results' => $this->searchActivities($query, $user)],
        ];

        if ($user !== null && $user->can('users.manage')) {
            $groups[] = ['key' => 'personen', 'label' => 'Beheer: personen', 'results' => $this->searchPersons($query)];
        }

        return array_values(array_filter($groups, fn (array $group): bool => $group['results'] !== []));
    }

    /**
     * Zoektermen (woordaanvullingen) uit titels van voor $user zichtbare
     * gepubliceerde pagina's en activiteiten.
     *
     * @return list<string>
     */
    public function suggestTerms(string $prefix, ?User $user, int $limit = 6): array
    {
        $prefix = trim($prefix);
        if (mb_strlen($prefix) < self::MIN_LENGTH) {
            return [];
        }

        $pageTitles = Page::query()
            ->whereNotNull('published_version_id')
            ->tap(fn (Builder $q) => $this->whereLike($q, 'title', $prefix))
            ->limit(200)
            ->get()
            ->filter(fn (Page $page): bool => $page->isVisibleTo($user))
            ->pluck('title')
            ->all();

        $activityTitles = $this->visibleActivities($user)
            ->tap(fn (Builder $q) => $this->whereLike($q, 'title', $prefix))
            ->limit(200)
            ->pluck('title')
            ->all();

        $needle = mb_strtolower($prefix);
        $counts = [];
        foreach ([...$pageTitles, ...$activityTitles] as $title) {
            foreach (preg_split('/[^\p{L}\p{N}]+/u', (string) $title, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
                $word = mb_strtolower($word);
                if (mb_strlen($word) >= 3 && str_starts_with($word, $needle)) {
                    $counts[$word] = ($counts[$word] ?? 0) + 1;
                }
            }
        }

        uksort($counts, fn (int|string $a, int|string $b): int => [$counts[$b], (string) $a] <=> [$counts[$a], (string) $b]);

        return array_slice(array_map('strval', array_keys($counts)), 0, $limit);
    }

    /**
     * @return list<SearchResult>
     */
    private function searchPages(string $query, ?User $user): array
    {
        $candidates = Page::query()
            ->whereNotNull('published_version_id')
            ->where(function (Builder $q) use ($query): void {
                $this->whereLike($q, 'title', $query);
                $q->orWhereHas('publishedVersion.bands.blocks', function (Builder $blocks) use ($query): void {
                    $this->whereLike($blocks, 'blocks.content', $query, jsonEncoded: true);
                });
            })
            ->with(['publishedVersion.bands.blocks', 'parent'])
            ->orderBy('title')
            ->limit(200)
            ->get();

        $results = [];
        foreach ($candidates as $page) {
            if (! $page->isVisibleTo($user)) {
                continue;
            }

            $text = $this->pageText($page);
            if (mb_stripos($page->title, $query) === false && mb_stripos($text, $query) === false) {
                continue;
            }

            $results[] = new SearchResult(
                title: $page->title,
                url: $page->publicUrl(),
                snippet: $this->snippet($text, $query),
            );

            if (count($results) >= self::PAGE_LIMIT) {
                break;
            }
        }

        return $results;
    }

    private function pageText(Page $page): string
    {
        $parts = [];
        foreach ($page->publishedVersion->bands as $band) {
            foreach ($band->blocks as $block) {
                $this->collectStrings($block->content, $parts);
            }
        }

        return $this->plainText(implode(' ', $parts));
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @param  list<string>  $into
     */
    private function collectStrings(array $value, array &$into): void
    {
        foreach ($value as $item) {
            if (is_array($item)) {
                $this->collectStrings($item, $into);
            } elseif (is_string($item)) {
                $into[] = $item;
            }
        }
    }

    /**
     * @return list<SearchResult>
     */
    private function searchActivities(string $query, ?User $user): array
    {
        $matches = function (Builder $q) use ($query): void {
            $q->where(function (Builder $inner) use ($query): void {
                $this->whereLike($inner, 'title', $query);
                $this->whereLike($inner, 'description', $query, boolean: 'or');
                $this->whereLike($inner, 'location', $query, boolean: 'or');
            });
        };

        $upcoming = $this->visibleActivities($user)
            ->tap($matches)
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')
            ->limit(self::ACTIVITY_LIMIT)
            ->get();

        $past = $upcoming->count() < self::ACTIVITY_LIMIT
            ? $this->visibleActivities($user)
                ->tap($matches)
                ->where('starts_at', '<', now())
                ->orderByDesc('starts_at')
                ->limit(self::ACTIVITY_LIMIT - $upcoming->count())
                ->get()
            : collect();

        return $upcoming->concat($past)
            ->map(fn (Activity $activity): SearchResult => new SearchResult(
                title: $activity->title,
                url: route('activiteit.show', $activity),
                meta: collect([
                    $activity->starts_at->locale('nl')->isoFormat('dddd D MMMM YYYY [om] HH:mm'),
                    $activity->location,
                ])->filter()->implode(' · '),
                snippet: $this->snippet($this->plainText((string) $activity->description), $query),
            ))
            ->values()
            ->all();
    }

    /**
     * Zelfde zichtbaarheid als de agenda (AgendaBlock::render()).
     *
     * @return Builder<Activity>
     */
    private function visibleActivities(?User $user): Builder
    {
        $query = Activity::query()->where('status', ActivityStatus::Published->value);

        if ($user === null) {
            $query->where('visibility', ActivityVisibility::Public->value);
        } elseif (! $user->can('activities.view')) {
            $query->whereIn('visibility', [ActivityVisibility::Public->value, ActivityVisibility::Members->value]);
        }

        return $query;
    }

    /**
     * @return list<SearchResult>
     */
    private function searchPersons(string $query): array
    {
        return Person::query()
            ->where(function (Builder $q) use ($query): void {
                foreach (['first_name', 'last_name_prefix', 'last_name', 'email'] as $column) {
                    $this->whereLike($q, $column, $query, boolean: 'or');
                }
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(self::PERSON_LIMIT)
            ->get()
            ->map(fn (Person $person): SearchResult => new SearchResult(
                title: $person->fullName(),
                url: route('admin.users.index', ['zoek' => $person->fullName()]),
                meta: $person->email,
            ))
            ->values()
            ->all();
    }

    /**
     * Voegt een LIKE-voorwaarde met geëscapete wildcards toe. Met $jsonEncoded
     * wordt ook de JSON-geëscapete variant (é, \/) gezocht, omdat
     * blokinhoud zo in de database kan staan.
     *
     * @param  Builder<*>  $query
     */
    private function whereLike(Builder $query, string $column, string $term, string $boolean = 'and', bool $jsonEncoded = false): void
    {
        $variants = [$term];
        if ($jsonEncoded) {
            $encoded = substr((string) json_encode($term), 1, -1);
            if ($encoded !== $term) {
                $variants[] = $encoded;
            }
        }

        $wrapped = $query->getQuery()->getGrammar()->wrap($column);

        $query->where(function (Builder $q) use ($wrapped, $variants): void {
            foreach ($variants as $variant) {
                $q->orWhereRaw(
                    $wrapped.' like ? escape \''.self::LIKE_ESCAPE.'\'',
                    ['%'.$this->escapeLike($variant).'%'],
                );
            }
        }, boolean: $boolean);
    }

    private function escapeLike(string $value): string
    {
        $escape = self::LIKE_ESCAPE;

        return str_replace(
            [$escape, '%', '_'],
            [$escape.$escape, $escape.'%', $escape.'_'],
            $value,
        );
    }

    private function plainText(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    private function snippet(string $text, string $query): ?string
    {
        if ($text === '') {
            return null;
        }

        $position = mb_stripos($text, $query);
        if ($position === false) {
            return null;
        }

        $start = max(0, $position - intdiv(self::SNIPPET_LENGTH - mb_strlen($query), 2));
        $snippet = mb_substr($text, $start, self::SNIPPET_LENGTH);

        return ($start > 0 ? '…' : '').$snippet.($start + self::SNIPPET_LENGTH < mb_strlen($text) ? '…' : '');
    }
}
