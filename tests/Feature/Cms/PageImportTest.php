<?php

use App\Enums\BandLayout;
use App\Enums\BlockType;
use App\Enums\PageType;
use App\Enums\PageVersionStatus;
use App\Enums\PageVisibility;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Template;

beforeEach(function () {
    config()->set('services.rzvg_import.token', 'geheime-import-token');

    $this->template = Template::create([
        'name' => 'Standaard',
        'zones' => [['key' => 'hoofd', 'label' => 'Hoofd']],
    ]);
});

function samplePayload(): array
{
    return [
        'page' => [
            'slug' => 'nieuwe-pagina',
            'title' => 'Nieuwe pagina',
            'type' => PageType::Content->value,
            'visibility' => PageVisibility::Public->value,
            'parent_slug' => null,
            'template_name' => 'Standaard',
        ],
        'version' => [
            'bands' => [
                [
                    'zone' => 'hoofd',
                    'layout' => BandLayout::OneColumn->value,
                    'sort_order' => 0,
                    'blocks' => [
                        [
                            'type' => BlockType::Text->value,
                            'column_index' => 0,
                            'sort_order' => 0,
                            'content' => ['html' => '<p>Hallo</p>'],
                            'visibility' => PageVisibility::Public->value,
                        ],
                    ],
                ],
            ],
        ],
    ];
}

it('weigert import zonder bearer token', function () {
    $this->postJson('/api/pages/import', samplePayload())->assertStatus(401);
});

it('weigert import met een verkeerde bearer token', function () {
    $this->withToken('fout')
        ->postJson('/api/pages/import', samplePayload())
        ->assertStatus(401);
});

it('geeft 503 als de import-token op de omgeving niet is ingesteld', function () {
    config()->set('services.rzvg_import.token', null);

    $this->withToken('geheime-import-token')
        ->postJson('/api/pages/import', samplePayload())
        ->assertStatus(503);
});

it('creëert een nieuwe pagina met conceptversie bij een nog onbekende slug', function () {
    $response = $this->withToken('geheime-import-token')
        ->postJson('/api/pages/import', samplePayload())
        ->assertStatus(201);

    $response->assertJson(['status' => 'ok', 'created' => true]);

    $page = Page::query()->where('slug', 'nieuwe-pagina')->firstOrFail();
    expect($page->title)->toBe('Nieuwe pagina')
        ->and($page->template_id)->toBe($this->template->id)
        ->and($page->versions()->count())->toBe(1);

    $version = $page->versions()->first();
    expect($version->status)->toBe(PageVersionStatus::Draft)
        ->and($version->bands()->count())->toBe(1);
});

it('voegt een nieuwe conceptversie toe onder een bestaande pagina bij slug-conflict', function () {
    $page = Page::query()->create([
        'slug' => 'nieuwe-pagina',
        'title' => 'Bestaand',
        'type' => PageType::Content,
        'visibility' => PageVisibility::Public,
        'template_id' => $this->template->id,
    ]);
    PageVersion::query()->create([
        'page_id' => $page->id,
        'version_no' => 1,
        'status' => PageVersionStatus::Published,
    ]);
    $page->update(['published_version_id' => $page->versions()->first()->id]);

    $this->withToken('geheime-import-token')
        ->postJson('/api/pages/import', samplePayload())
        ->assertStatus(200)
        ->assertJson(['status' => 'ok', 'created' => false]);

    $page->refresh();
    expect($page->title)->toBe('Bestaand')
        ->and($page->versions()->count())->toBe(2);
});

it('weigert import als het template op de doel-omgeving onbekend is', function () {
    $payload = samplePayload();
    $payload['page']['template_name'] = 'Onbekend';

    $this->withToken('geheime-import-token')
        ->postJson('/api/pages/import', $payload)
        ->assertStatus(422);
});
