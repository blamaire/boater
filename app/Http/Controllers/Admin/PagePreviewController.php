<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\PageVersion;
use Illuminate\Contracts\View\View;

class PagePreviewController extends Controller
{
    public function __invoke(Page $page, PageVersion $version): View
    {
        abort_unless($version->page_id === $page->id, 404);

        $version->load(['bands.blocks']);

        return view('public.page', [
            'page' => $page,
            'version' => $version,
            'preview' => true,
            'previewLabel' => "Voorvertoning van v{$version->version_no} (".ucfirst(str_replace('_', ' ', $version->status->value)).')',
        ]);
    }
}
