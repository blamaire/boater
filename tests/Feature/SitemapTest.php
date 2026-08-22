<?php

use App\Enums\PageType;
use App\Enums\PageVersionStatus;
use App\Enums\PageVisibility;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Template;

beforeEach(function () {
    $this->template = Template::create([
        'name' => 'Standaard',
        'zones' => [['key' => 'hoofd', 'label' => 'Hoofd']],
    ]);
});

function sitemapPage(Template $template, string $slug, PageVisibility $visibility = PageVisibility::Public, PageType $type = PageType::Content, bool $published = true): Page
{
    $page = Page::create([
        'slug' => $slug,
        'title' => $slug,
        'type' => $type,
        'visibility' => $visibility,
        'template_id' => $template->id,
    ]);

    if ($published) {
        $version = PageVersion::create([
            'page_id' => $page->id,
            'version_no' => 1,
            'status' => PageVersionStatus::Published,
        ]);
        $page->update(['published_version_id' => $version->id]);
    }

    return $page;
}

it('geeft content-type application/xml terug', function () {
    $this->get('/sitemap.xml')->assertHeader('Content-Type', 'application/xml');
});

it('bevat alleen publieke, gepubliceerde pagina’s', function () {
    $page = sitemapPage($this->template, 'over-ons');

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertSee(rtrim(config('app.url'), '/').$page->publicUrl(), false);
});

it('sluit beperkt-zichtbare pagina’s uit', function () {
    sitemapPage($this->template, 'voor-leden', visibility: PageVisibility::Restricted);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertDontSee('voor-leden');
});

it('sluit pagina’s zonder gepubliceerde versie uit', function () {
    sitemapPage($this->template, 'nog-concept', published: false);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertDontSee('nog-concept');
});

it('levert / als loc voor de systeem-homepagina', function () {
    sitemapPage($this->template, 'home', type: PageType::System);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertSee('<loc>'.rtrim(config('app.url'), '/').'/</loc>', false);
});
