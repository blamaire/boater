<?php

namespace App\Livewire\Admin;

use App\Enums\PageType;
use App\Enums\PageVersionStatus;
use App\Models\ActivityPage;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Template;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Beheer-UI voor activiteitenpagina's: een thematische, periodiek terugkerende
 * happening (bv. "Zomerkamp") met een eigen CMS-infopagina, waar losse
 * activiteiten bij kunnen horen (`Activity::activity_page_id`). Toegang
 * loopt mee met `activities.update` — een event is conceptueel onderdeel van
 * de Activiteiten-module, net als categorieën.
 */
#[Layout('layouts.app', ['header' => "Activiteitenpagina's"])]
class ActivityPageBeheer extends Component
{
    public ?int $editingId = null;

    public string $title = '';

    public ?string $statusMessage = null;

    public function edit(int $id): void
    {
        $event = ActivityPage::query()->with('page')->findOrFail($id);
        $this->editingId = $event->id;
        $this->title = $event->page->title;
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'title']);
    }

    public function save(AuditLogger $audit): void
    {
        $this->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        if ($this->editingId === null) {
            $this->create($audit);
        } else {
            $this->updateTitle($audit);
        }

        $this->resetForm();
    }

    private function create(AuditLogger $audit): void
    {
        $slug = $this->uniqueSlug(Str::slug($this->title));
        $personId = auth()->user()?->person?->id;

        DB::transaction(function () use ($slug, $personId, $audit): void {
            $page = Page::query()->create([
                'slug' => $slug,
                'title' => $this->title,
                'type' => PageType::Content,
                'template_id' => Template::query()->orderBy('id')->value('id'),
            ]);

            PageVersion::query()->create([
                'page_id' => $page->id,
                'version_no' => 1,
                'status' => PageVersionStatus::Draft,
                'created_by_person_id' => $personId,
            ]);

            $event = ActivityPage::query()->create([
                'page_id' => $page->id,
                'created_by_person_id' => $personId,
            ]);

            $audit->log('activity_page.created', $event, after: ['title' => $this->title, 'slug' => $slug]);
            $this->statusMessage = "Activiteitenpagina [{$this->title}] aangemaakt — voeg nu inhoud toe aan de infopagina.";
        });
    }

    private function updateTitle(AuditLogger $audit): void
    {
        $event = ActivityPage::query()->with('page')->findOrFail($this->editingId);
        $before = ['title' => $event->page->title];
        $event->page->update(['title' => $this->title]);
        $audit->log('activity_page.updated', $event, before: $before, after: ['title' => $this->title]);
        $this->statusMessage = "Activiteitenpagina [{$this->title}] bijgewerkt.";
    }

    private function uniqueSlug(string $base): string
    {
        $slug = $base;
        $suffix = 2;
        while (Page::query()->whereNull('parent_id')->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    public function delete(int $id, AuditLogger $audit): void
    {
        $event = ActivityPage::query()->with('page')->findOrFail($id);

        if ($event->activities()->exists()) {
            $this->statusMessage = "Event [{$event->page->title}] kan niet worden verwijderd — er zijn nog activiteiten aan gekoppeld.";

            return;
        }

        $before = ['title' => $event->page->title];
        DB::transaction(function () use ($event, $before, $audit): void {
            $audit->log('activity_page.deleted', $event, before: $before);
            $event->delete();
        });
        $this->statusMessage = "Event [{$before['title']}] verwijderd (de infopagina blijft bestaan onder Pagina's).";
    }

    public function render(): View
    {
        return view('livewire.admin.activity-page-beheer', [
            'events' => ActivityPage::query()
                ->with('page')
                ->withCount('activities')
                ->get()
                ->sortBy(fn (ActivityPage $event) => $event->page->title)
                ->values(),
        ]);
    }
}
