<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PageVersionStatus;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\PageVersion;
use App\Services\Cms\ConflictDetector;
use App\Services\Cms\PageVersionDiffSerializer;
use App\Services\Cms\TextDiffer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PageHistoryController extends Controller
{
    public function index(Page $page): View
    {
        $versions = $page->versions()->with('createdBy')->get();

        return view('admin.pages.history', [
            'page' => $page,
            'versions' => $versions,
        ]);
    }

    public function diff(
        Page $page,
        PageVersion $version,
        PageVersion $other,
        ConflictDetector $detector,
        PageVersionDiffSerializer $serializer,
        TextDiffer $textDiffer,
    ): View {
        abort_unless($version->page_id === $page->id, 404);
        abort_unless($other->page_id === $page->id, 404);

        // Two-way diff: geen gemeenschappelijke voorouder, dus base=null.
        $report = $detector->detect($version, $other, null);

        $rawAJson = (string) json_encode($serializer->raw($version), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $rawBJson = (string) json_encode($serializer->raw($other), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return view('admin.pages.history-diff', [
            'page' => $page,
            'a' => $version,
            'b' => $other,
            'report' => $report,
            'structuredDiff' => $serializer->structured($report),
            'rawAJson' => $rawAJson,
            'rawBJson' => $rawBJson,
            'textDiff' => $textDiffer->diffLines($rawAJson, $rawBJson),
        ]);
    }

    public function restore(Request $request, Page $page, PageVersion $version): RedirectResponse
    {
        abort_unless($version->page_id === $page->id, 404);

        $person = $request->user()?->person;
        abort_unless($person !== null, 403, 'Account is niet gekoppeld aan een persoon.');

        $latest = PageVersion::query()
            ->where('page_id', $page->id)
            ->orderByDesc('version_no')
            ->first();
        $nextVersionNo = ($latest !== null ? $latest->version_no : 0) + 1;

        $baseVersionId = $version->id;
        if ($page->publishedVersion !== null) {
            $baseVersionId = $page->publishedVersion->id;
        }

        $newVersion = PageVersion::create([
            'page_id' => $page->id,
            'version_no' => $nextVersionNo,
            'status' => PageVersionStatus::Draft,
            'base_version_id' => $baseVersionId,
            'created_by_person_id' => $person->id,
        ]);

        foreach ($version->bands()->with('blocks')->get() as $band) {
            $newBand = $newVersion->bands()->create([
                'origin_band_id' => $band->origin_band_id ?? $band->id,
                'zone' => $band->zone,
                'layout' => $band->layout,
                'sort_order' => $band->sort_order,
            ]);

            foreach ($band->blocks as $block) {
                $newBand->blocks()->create([
                    'origin_block_id' => $block->origin_block_id ?? $block->id,
                    'column_index' => $block->column_index,
                    'sort_order' => $block->sort_order,
                    'type' => $block->type,
                    'content' => $block->content,
                    'visibility' => $block->visibility,
                ]);
            }
        }

        return redirect()->route('admin.pages.editor', $page)
            ->with('status', "Nieuwe conceptversie op basis van v{$version->version_no} aangemaakt.");
    }
}
