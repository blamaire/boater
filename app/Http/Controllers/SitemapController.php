<?php

namespace App\Http\Controllers;

use App\Enums\PageVisibility;
use App\Models\Page;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $pages = Page::query()
            ->where('visibility', PageVisibility::Public->value)
            ->whereNotNull('published_version_id')
            ->with('publishedVersion')
            ->get();

        return response()
            ->view('sitemap', ['pages' => $pages])
            ->header('Content-Type', 'application/xml');
    }
}
