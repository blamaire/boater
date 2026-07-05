<?php

use App\Enums\BandLayout;
use App\Enums\BlockType;
use App\Enums\PageType;
use App\Enums\PageVersionStatus;
use App\Enums\PageVisibility;
use App\Models\Band;
use App\Models\Block;
use App\Models\Environment;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Template;
use App\Services\Cms\PagePushService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->template = Template::create([
        'name' => 'Standaard',
        'zones' => [['key' => 'hoofd', 'label' => 'Hoofd']],
    ]);

    $this->page = Page::query()->create([
        'slug' => 'over',
        'title' => 'Over',
        'type' => PageType::Content,
        'visibility' => PageVisibility::Public,
        'template_id' => $this->template->id,
    ]);

    $version = PageVersion::query()->create([
        'page_id' => $this->page->id,
        'version_no' => 1,
        'status' => PageVersionStatus::Published,
    ]);
    $band = Band::query()->create([
        'page_version_id' => $version->id,
        'zone' => 'hoofd',
        'layout' => BandLayout::OneColumn,
        'sort_order' => 0,
    ]);
    Block::query()->create([
        'band_id' => $band->id,
        'column_index' => 0,
        'sort_order' => 0,
        'type' => BlockType::Text,
        'content' => ['html' => '<p>Welkom</p>'],
        'visibility' => PageVisibility::Public,
    ]);
    $this->page->update(['published_version_id' => $version->id]);
});

it('stuurt gepubliceerde inhoud met bearer token naar het import-endpoint', function () {
    $env = Environment::query()->create([
        'name' => 'test',
        'url' => 'https://rzvg-tst.lamaire.nl/',
        'api_token' => 'test-token',
        'is_active' => true,
    ]);

    Http::fake([
        'rzvg-tst.lamaire.nl/api/pages/import' => Http::response(['status' => 'ok', 'created' => true], 201),
    ]);

    $result = app(PagePushService::class)->push($this->page->fresh(), $env);

    expect($result['created'])->toBeTrue();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://rzvg-tst.lamaire.nl/api/pages/import'
            && $request->hasHeader('Authorization', 'Bearer test-token')
            && $request['page']['slug'] === 'over'
            && $request['page']['template_name'] === 'Standaard'
            && count($request['version']['bands']) === 1
            && $request['version']['bands'][0]['blocks'][0]['content']['html'] === '<p>Welkom</p>';
    });
});

it('weigert push naar een inactieve omgeving', function () {
    $env = Environment::query()->create([
        'name' => 'test',
        'url' => 'https://rzvg-tst.lamaire.nl',
        'api_token' => 'test-token',
        'is_active' => false,
    ]);

    expect(fn () => app(PagePushService::class)->push($this->page->fresh(), $env))
        ->toThrow(RuntimeException::class);
});

it('weigert push van een pagina zonder gepubliceerde versie', function () {
    $this->page->update(['published_version_id' => null]);

    $env = Environment::query()->create([
        'name' => 'test',
        'url' => 'https://rzvg-tst.lamaire.nl',
        'api_token' => 'test-token',
        'is_active' => true,
    ]);

    expect(fn () => app(PagePushService::class)->push($this->page->fresh(), $env))
        ->toThrow(RuntimeException::class);
});

it('maakt een fout duidelijk als target http-fout teruggeeft', function () {
    $env = Environment::query()->create([
        'name' => 'test',
        'url' => 'https://rzvg-tst.lamaire.nl',
        'api_token' => 'test-token',
        'is_active' => true,
    ]);

    Http::fake([
        '*' => Http::response(['message' => 'kapot'], 422),
    ]);

    expect(fn () => app(PagePushService::class)->push($this->page->fresh(), $env))
        ->toThrow(RuntimeException::class);
});
