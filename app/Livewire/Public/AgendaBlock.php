<?php

namespace App\Livewire\Public;

use App\Enums\ActivityStatus;
use App\Enums\ActivityVisibility;
use App\Models\Activity;
use App\Models\ActivityCategory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Component;

/**
 * Publieke agenda-lijst voor een CMS AgendaBlock. Neemt een voorfilter uit
 * de block-content en biedt daarbovenop een user-filter (categorie, historie
 * verbergen) mits de beheerder dat op het block heeft aangezet.
 *
 * @property-read array<string, mixed> $blockContent
 */
class AgendaBlock extends Component
{
    /** @var array<string, mixed> */
    public array $blockContent = [];

    /** @var array<int, int> */
    public array $userCategoryIds = [];

    public bool $userHideHistory = true;

    public string $userSearch = '';

    /**
     * @param  array<string, mixed>  $blockContent
     */
    public function mount(array $blockContent): void
    {
        $this->blockContent = $blockContent;
        $this->userHideHistory = (bool) ($blockContent['hide_history'] ?? true);
    }

    public function render(): View
    {
        $preCategoryIds = array_map('intval', $this->blockContent['category_ids'] ?? []);
        $periodDays = (int) ($this->blockContent['period_days'] ?? 0);
        $limit = max(1, (int) ($this->blockContent['limit'] ?? 20));
        $allowUserFilter = (bool) ($this->blockContent['allow_user_filter'] ?? true);

        $query = Activity::query()
            ->with('category')
            ->where('status', ActivityStatus::Published->value)
            ->orderBy('starts_at');

        // Zichtbaarheid volgt de bezoeker: niet-ingelogd → alleen public;
        // ingelogd → public + members; met activities.view (beheer) → alles.
        $user = auth()->user();
        if ($user === null) {
            $query->where('visibility', ActivityVisibility::Public->value);
        } elseif (! $user->can('activities.view')) {
            $query->whereIn('visibility', [ActivityVisibility::Public->value, ActivityVisibility::Members->value]);
        }

        if ($preCategoryIds !== []) {
            $query->whereIn('activity_category_id', $preCategoryIds);
        }

        if ($periodDays > 0) {
            $query->where('starts_at', '<=', Carbon::now()->addDays($periodDays)->endOfDay());
        }

        if (($this->userHideHistory || (bool) ($this->blockContent['hide_history'] ?? true)) === true) {
            $query->where('starts_at', '>=', Carbon::now()->startOfDay());
        }

        if ($allowUserFilter && $this->userCategoryIds !== []) {
            // User-filter kan alleen inperken binnen wat het voorfilter toelaat.
            $allowed = $preCategoryIds === []
                ? array_map('intval', $this->userCategoryIds)
                : array_intersect(array_map('intval', $this->userCategoryIds), $preCategoryIds);
            if ($allowed !== []) {
                $query->whereIn('activity_category_id', $allowed);
            }
        }

        if ($allowUserFilter && trim($this->userSearch) !== '') {
            $like = '%'.trim($this->userSearch).'%';
            $query->where(function ($q) use ($like): void {
                $q->where('title', 'like', $like)
                    ->orWhere('location', 'like', $like);
            });
        }

        $activities = $query->limit($limit)->get();

        // Categorielijst voor het user-filter (alleen die uit voorfilter, of alles).
        $filterCategories = ActivityCategory::query()
            ->when($preCategoryIds !== [], fn ($q) => $q->whereIn('id', $preCategoryIds))
            ->orderBy('sort_order')
            ->get();

        return view('livewire.public.agenda-block', [
            'activities' => $activities,
            'filterCategories' => $filterCategories,
            'allowUserFilter' => $allowUserFilter,
        ]);
    }
}
