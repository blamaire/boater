<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PageType;
use App\Enums\PageVersionStatus;
use App\Enums\PageVisibility;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Template;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PageController extends Controller
{
    public function index(): View
    {
        $pages = Page::query()
            ->with(['template', 'parent', 'publishedVersion'])
            ->orderBy('parent_id')
            ->orderBy('title')
            ->get();

        return view('admin.pages.index', [
            'pages' => $pages,
        ]);
    }

    public function create(): View
    {
        return view('admin.pages.create', [
            'templates' => Template::query()->orderBy('name')->get(),
            'parents' => Page::query()->orderBy('title')->get(),
            'visibilities' => PageVisibility::cases(),
            'types' => PageType::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request, page: null);

        $page = DB::transaction(function () use ($data, $request) {
            $page = Page::create([
                'slug' => $data['slug'],
                'title' => $data['title'],
                'type' => PageType::Content,
                'visibility' => $data['visibility'],
                'parent_id' => $data['parent_id'],
                'template_id' => $data['template_id'],
            ]);

            PageVersion::create([
                'page_id' => $page->id,
                'version_no' => 1,
                'status' => PageVersionStatus::Draft,
                'created_by_person_id' => $request->user()?->person?->id,
            ]);

            return $page;
        });

        return redirect()
            ->route('admin.pages.editor', $page)
            ->with('status', 'Pagina aangemaakt — je kunt nu inhoud toevoegen.');
    }

    public function edit(Page $page): View
    {
        return view('admin.pages.edit', [
            'page' => $page,
            'templates' => Template::query()->orderBy('name')->get(),
            'parents' => Page::query()
                ->where('id', '!=', $page->id)
                ->orderBy('title')
                ->get(),
            'visibilities' => PageVisibility::cases(),
            'types' => PageType::cases(),
        ]);
    }

    public function update(Request $request, Page $page): RedirectResponse
    {
        $data = $this->validateData($request, page: $page);

        $page->update([
            'slug' => $data['slug'],
            'title' => $data['title'],
            'visibility' => $data['visibility'],
            'parent_id' => $data['parent_id'],
            'template_id' => $data['template_id'],
        ]);

        return redirect()
            ->route('admin.pages.edit', $page)
            ->with('status', 'Instellingen bijgewerkt.');
    }

    public function destroy(Page $page): RedirectResponse
    {
        if (! $page->type->isDeletable()) {
            return redirect()
                ->route('admin.pages.index')
                ->with('error', 'Systeempagina\'s kunnen niet worden verwijderd.');
        }

        $page->delete();

        return redirect()
            ->route('admin.pages.index')
            ->with('status', 'Pagina verwijderd.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request, ?Page $page): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('pages')
                    ->where(fn ($q) => $q->where('parent_id', $request->input('parent_id')))
                    ->ignore($page?->id),
            ],
            'visibility' => ['required', Rule::enum(PageVisibility::class)],
            'parent_id' => ['nullable', 'integer', Rule::exists('pages', 'id')],
            'template_id' => ['required', 'integer', Rule::exists('templates', 'id')],
        ], [
            'slug.regex' => 'Een slug bevat alleen kleine letters, cijfers en streepjes.',
        ]);
    }
}
