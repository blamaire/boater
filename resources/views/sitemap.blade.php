{{ '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' }}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    @foreach ($pages as $page)
        <url>
            <loc>{{ rtrim(config('app.url'), '/').$page->publicUrl() }}</loc>
            @if ($page->publishedVersion)
                <lastmod>{{ $page->publishedVersion->updated_at->toAtomString() }}</lastmod>
            @endif
        </url>
    @endforeach
</urlset>
