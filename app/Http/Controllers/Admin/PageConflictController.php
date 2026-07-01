<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\PageVersion;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class PageConflictController extends Controller
{
    public function show(Request $request, Page $page, PageVersion $version, PageVersion $other): View
    {
        abort_unless($version->page_id === $page->id, 404);
        abort_unless($other->page_id === $page->id, 404);

        return view('admin.pages.conflict', [
            'page' => $page,
            'mine' => $version,
            'theirs' => $other,
        ]);
    }
}
