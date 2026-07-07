<?php

use App\Enums\BandLayout;
use App\Enums\BlockType;
use App\Enums\MediaType;
use App\Enums\PageType;
use App\Enums\PageVersionStatus;
use App\Enums\PageVisibility;
use App\Models\Band;
use App\Models\Block;
use App\Models\Environment;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Template;
use App\Services\Cms\PageImportService;
use App\Services\Cms\PagePushService;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    config()->set('services.rzvg_import.token', 'geheime-import-token');
    Storage::fake('media');

    $this->template = Template::create([
        'name' => 'Standaard',
        'zones' => [['key' => 'hoofd', 'label' => 'Hoofd']],
    ]);
});

it('geeft elke nieuwe MediaAsset een uuid via het creating-event', function () {
    $asset = MediaAsset::create([
        'disk' => 'media',
        'path' => 'x.jpg',
        'original_name' => 'x.jpg',
        'mime_type' => 'image/jpeg',
        'type' => MediaType::Image,
        'file_size' => 100,
        'visibility' => PageVisibility::Public,
    ]);

    expect($asset->uuid)->toBeString()
        ->and(strlen($asset->uuid))->toBe(36);
});

it('probe-endpoint retourneert alleen ontbrekende uuids', function () {
    $bestaand = MediaAsset::create([
        'disk' => 'media',
        'path' => 'x.jpg',
        'original_name' => 'x.jpg',
        'mime_type' => 'image/jpeg',
        'type' => MediaType::Image,
        'file_size' => 100,
        'visibility' => PageVisibility::Public,
    ]);
    $onbekendUuid = (string) Str::uuid();

    $this->withToken('geheime-import-token')
        ->postJson('/api/media/probe', ['uuids' => [$bestaand->uuid, $onbekendUuid]])
        ->assertOk()
        ->assertJson(['missing_uuids' => [$onbekendUuid]]);
});

it('upload-endpoint slaat een nieuwe asset op met de meegegeven uuid', function () {
    $uuid = (string) Str::uuid();

    $this->withToken('geheime-import-token')
        ->post('/api/media/upload', [
            'uuid' => $uuid,
            'original_name' => 'foto.jpg',
            'mime_type' => 'image/jpeg',
            'type' => MediaType::Image->value,
            'file_size' => 12345,
            'alt' => 'Testfoto',
            'visibility' => PageVisibility::Public->value,
            'file' => File::create('foto.jpg', 10),
        ])
        ->assertStatus(201)
        ->assertJson(['uuid' => $uuid, 'existed' => false]);

    expect(MediaAsset::query()->where('uuid', $uuid)->exists())->toBeTrue();
});

it('upload-endpoint is idempotent voor een bekende uuid', function () {
    $asset = MediaAsset::create([
        'disk' => 'media',
        'path' => 'x.jpg',
        'original_name' => 'x.jpg',
        'mime_type' => 'image/jpeg',
        'type' => MediaType::Image,
        'file_size' => 100,
        'visibility' => PageVisibility::Public,
    ]);

    $this->withToken('geheime-import-token')
        ->post('/api/media/upload', [
            'uuid' => $asset->uuid,
            'original_name' => 'x.jpg',
            'mime_type' => 'image/jpeg',
            'type' => MediaType::Image->value,
            'file_size' => 100,
            'visibility' => PageVisibility::Public->value,
            'file' => File::create('x.jpg', 10),
        ])
        ->assertOk()
        ->assertJson(['id' => $asset->id, 'existed' => true]);
});

it('vertaalt media_uuid_map naar lokale asset-IDs bij import', function () {
    $localAsset = MediaAsset::create([
        'disk' => 'media',
        'path' => 'ok.jpg',
        'original_name' => 'ok.jpg',
        'mime_type' => 'image/jpeg',
        'type' => MediaType::Image,
        'file_size' => 100,
        'visibility' => PageVisibility::Public,
    ]);
    $onbekendUuid = (string) Str::uuid();

    $payload = [
        'page' => [
            'slug' => 'test',
            'title' => 'Test',
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
                            'type' => BlockType::Hero->value,
                            'column_index' => 0,
                            'sort_order' => 0,
                            // source-id 42 → local $localAsset->id;
                            // source-id 99 → onbekend UUID → wordt null.
                            'content' => ['title' => 'H', 'media_asset_id' => 42, 'subtitle' => ''],
                            'visibility' => PageVisibility::Public->value,
                        ],
                        [
                            'type' => BlockType::Card->value,
                            'column_index' => 0,
                            'sort_order' => 1,
                            'content' => ['title' => 'C', 'image_media_asset_id' => 99, 'body' => ''],
                            'visibility' => PageVisibility::Public->value,
                        ],
                    ],
                ],
            ],
        ],
        'media_uuid_map' => [
            '42' => $localAsset->uuid,
            '99' => $onbekendUuid,
        ],
    ];

    app(PageImportService::class)->import($payload);

    $page = Page::query()->where('slug', 'test')->firstOrFail();
    $blocks = Block::query()->whereIn('band_id', $page->versions()->pluck('id'))->get();
    // Bovenstaande query pakt bands via version_id — simpelere aanpak:
    $version = $page->versions()->first();
    $blocks = Block::query()->whereIn('band_id', $version->bands()->pluck('id'))->get();

    $hero = $blocks->firstWhere('type', BlockType::Hero);
    $card = $blocks->firstWhere('type', BlockType::Card);

    expect($hero->content['media_asset_id'])->toBe($localAsset->id)
        ->and($card->content['image_media_asset_id'])->toBeNull();
});

it('push probeert ontbrekende uuids en stuurt binaries op vóór de import', function () {
    // Bereid een gepubliceerde pagina met hero-block dat naar een lokale asset verwijst.
    $asset = MediaAsset::create([
        'disk' => 'media',
        'path' => 'foto.jpg',
        'original_name' => 'foto.jpg',
        'mime_type' => 'image/jpeg',
        'type' => MediaType::Image,
        'file_size' => 4,
        'visibility' => PageVisibility::Public,
    ]);
    // Schrijf een lokaal bestand naar de gefaked media-disk zodat de push
    // 'm kan lezen voor de upload-call.
    Storage::disk('media')->put($asset->path, 'test');

    $page = Page::query()->create([
        'slug' => 'over',
        'title' => 'Over',
        'type' => PageType::Content,
        'visibility' => PageVisibility::Public,
        'template_id' => $this->template->id,
    ]);
    $version = PageVersion::query()->create([
        'page_id' => $page->id,
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
        'type' => BlockType::Hero,
        'content' => ['title' => 'H', 'media_asset_id' => $asset->id],
        'visibility' => PageVisibility::Public,
    ]);
    $page->update(['published_version_id' => $version->id]);

    $env = Environment::create([
        'name' => 'test',
        'url' => 'https://target.example',
        'api_token' => 'target-token',
        'is_active' => true,
    ]);

    Http::fake([
        'target.example/api/media/probe' => Http::response(['missing_uuids' => [$asset->uuid]]),
        'target.example/api/media/upload' => Http::response(['id' => 777, 'uuid' => $asset->uuid, 'existed' => false], 201),
        'target.example/api/pages/import' => Http::response(['status' => 'ok', 'created' => true], 201),
    ]);

    app(PagePushService::class)->push($page->fresh(), $env);

    // Verifieer dat de drie endpoints in de juiste volgorde zijn geraakt.
    Http::assertSentInOrder([
        fn ($req) => $req->url() === 'https://target.example/api/media/probe',
        fn ($req) => $req->url() === 'https://target.example/api/media/upload',
        fn ($req) => $req->url() === 'https://target.example/api/pages/import'
            && ($req['media_uuid_map'][(string) $asset->id] ?? null) === $asset->uuid,
    ]);
});
