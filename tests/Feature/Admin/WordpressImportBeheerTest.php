<?php

use App\Enums\PageType;
use App\Enums\PageVersionStatus;
use App\Enums\PageVisibility;
use App\Enums\WordpressContentType;
use App\Enums\WordpressImportStatus;
use App\Livewire\Admin\WordpressImportBeheer;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\Person;
use App\Models\Role;
use App\Models\Template;
use App\Models\User;
use App\Models\WordpressImportItem;
use App\Models\WordpressImportMediaItem;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TemplateSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(TemplateSeeder::class);
    Storage::fake('media');

    $this->beheerder = User::factory()->create(['email_verified_at' => now()]);
    Person::create(['first_name' => 'B', 'last_name' => 'Heer', 'account_id' => $this->beheerder->id])
        ->roles()->attach(Role::query()->where('name', 'Beheerder')->value('id'));
});

function makeWordpressImportItem(array $overrides = []): WordpressImportItem
{
    return WordpressImportItem::create(array_merge([
        'wordpress_id' => random_int(1000, 999999),
        'wordpress_type' => WordpressContentType::Page,
        'title' => 'Over ons',
        'slug' => 'over-ons',
        'content_html' => '<p>Dit is de over-ons-pagina.</p>',
        'excerpt' => null,
        'wordpress_published_at' => now()->subYear(),
        'status' => WordpressImportStatus::New,
        'raw_meta' => ['wp_status' => 'publish', 'categories' => [], 'tags' => []],
    ], $overrides));
}

function makeWordpressImportMediaItem(WordpressImportItem $item, array $overrides = []): WordpressImportMediaItem
{
    return WordpressImportMediaItem::create(array_merge([
        'wordpress_import_item_id' => $item->id,
        'wordpress_id' => random_int(1000, 999999),
        'title' => 'foto.jpg',
        'url' => 'https://oud.rzvg.nl/wp-content/uploads/foto.jpg',
        'mime_type' => 'image/jpeg',
        'selected' => true,
    ], $overrides));
}

it('vereist wordpress_import.manage permissie om de pagina te bezoeken', function () {
    $lid = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($lid)->get('/beheer/wordpress-import')->assertForbidden();
});

it('vereist wordpress_import.manage permissie om acties uit te voeren', function () {
    $lid = User::factory()->create(['email_verified_at' => now()]);
    Person::create(['first_name' => 'L', 'last_name' => 'Id', 'account_id' => $lid->id]);

    $item = makeWordpressImportItem();

    $this->actingAs($lid);

    Livewire::test(WordpressImportBeheer::class)
        ->call('takeOver', $item->id)
        ->assertForbidden();
});

it('toont geseede items en filtert op status en type', function () {
    $nieuw = makeWordpressImportItem(['title' => 'Nieuw item']);
    $gearchiveerd = makeWordpressImportItem(['title' => 'Gearchiveerd item', 'status' => WordpressImportStatus::Archived]);
    $bericht = makeWordpressImportItem(['title' => 'Berichtitem', 'wordpress_type' => WordpressContentType::Post]);

    $this->actingAs($this->beheerder);

    Livewire::test(WordpressImportBeheer::class)
        ->assertSee('Nieuw item')
        ->assertSee('Gearchiveerd item')
        ->assertSee('Berichtitem')
        ->set('filterStatus', WordpressImportStatus::Archived->value)
        ->assertSee('Gearchiveerd item')
        ->assertDontSee('Nieuw item')
        ->set('filterStatus', '')
        ->set('filterType', WordpressContentType::Post->value)
        ->assertSee('Berichtitem')
        ->assertDontSee('Nieuw item');

    expect($nieuw->exists)->toBeTrue()
        ->and($gearchiveerd->exists)->toBeTrue()
        ->and($bericht->exists)->toBeTrue();
});

it('neemt een nieuw item over als CMS-pagina in concept', function () {
    $item = makeWordpressImportItem(['title' => 'Over ons', 'slug' => 'over-ons', 'content_html' => '<p>Inhoud.</p>']);

    $this->actingAs($this->beheerder);

    $component = Livewire::test(WordpressImportBeheer::class)
        ->call('takeOver', $item->id);

    $item->refresh();
    expect($item->status)->toBe(WordpressImportStatus::Imported)
        ->and($item->page_id)->not->toBeNull();

    $page = Page::findOrFail($item->page_id);
    expect($page->type)->toBe(PageType::Content)
        ->and($page->visibility)->toBe(PageVisibility::Public)
        ->and($page->template_id)->toBe(Template::where('name', 'Standaard')->value('id'))
        ->and($page->published_version_id)->toBeNull();

    $version = $page->versions()->firstOrFail();
    expect($version->version_no)->toBe(1)
        ->and($version->status)->toBe(PageVersionStatus::Draft);

    $band = $version->bands()->firstOrFail();
    expect($band->zone)->toBe('hoofd');

    $block = $band->blocks()->firstOrFail();
    expect($block->type->value)->toBe('tekst')
        ->and($block->content['html'])->toBe('<p>Inhoud.</p>');

    $component->assertRedirect(route('admin.pages.editor', $page));
});

it('weigert een tweede takeOver op een al overgenomen item', function () {
    $item = makeWordpressImportItem();

    $this->actingAs($this->beheerder);

    Livewire::test(WordpressImportBeheer::class)->call('takeOver', $item->id);

    $pageCountAfterFirst = Page::count();

    $item->refresh();
    Livewire::test(WordpressImportBeheer::class)
        ->call('takeOver', $item->id)
        ->assertSet('errorMessage', fn ($value) => $value !== null);

    expect(Page::count())->toBe($pageCountAfterFirst);
});

it('archiveert een nieuw item zonder een pagina aan te maken', function () {
    $item = makeWordpressImportItem();
    $pageCountBefore = Page::count();

    $this->actingAs($this->beheerder);

    Livewire::test(WordpressImportBeheer::class)
        ->call('archive', $item->id)
        ->assertSet('statusMessage', fn ($value) => $value !== null);

    expect(Page::count())->toBe($pageCountBefore)
        ->and($item->refresh()->status)->toBe(WordpressImportStatus::Archived);
});

it('maakt een unieke slug bij een botsing met een bestaande pagina', function () {
    $template = Template::where('name', 'Standaard')->firstOrFail();
    Page::create([
        'slug' => 'over-ons',
        'title' => 'Over ons (bestaand)',
        'type' => PageType::Content,
        'visibility' => PageVisibility::Public,
        'parent_id' => null,
        'template_id' => $template->id,
    ]);

    $item = makeWordpressImportItem(['slug' => 'over-ons', 'title' => 'Over ons']);

    $this->actingAs($this->beheerder);

    Livewire::test(WordpressImportBeheer::class)->call('takeOver', $item->id);

    $item->refresh();
    $page = Page::findOrFail($item->page_id);
    expect($page->slug)->toBe('over-ons-2');
});

it('zet een gearchiveerd item terug naar nieuw', function () {
    $item = makeWordpressImportItem(['status' => WordpressImportStatus::Archived]);

    $this->actingAs($this->beheerder);

    Livewire::test(WordpressImportBeheer::class)->call('restoreToNew', $item->id);

    expect($item->refresh()->status)->toBe(WordpressImportStatus::New);
});

it('schakelt de selectie van een bijlage om voor een nieuw item', function () {
    $item = makeWordpressImportItem();
    $media = makeWordpressImportMediaItem($item, ['selected' => true]);

    $this->actingAs($this->beheerder);

    Livewire::test(WordpressImportBeheer::class)->call('toggleMedia', $media->id);
    expect($media->refresh()->selected)->toBeFalse();

    Livewire::test(WordpressImportBeheer::class)->call('toggleMedia', $media->id);
    expect($media->refresh()->selected)->toBeTrue();
});

it('weigert het omschakelen van media op een al overgenomen item', function () {
    $item = makeWordpressImportItem(['status' => WordpressImportStatus::Imported]);
    $media = makeWordpressImportMediaItem($item);

    $this->actingAs($this->beheerder);

    Livewire::test(WordpressImportBeheer::class)
        ->call('toggleMedia', $media->id)
        ->assertForbidden();
});

it('downloadt geselecteerde media bij overnemen en herschrijft de content-URL', function () {
    $fakeImage = UploadedFile::fake()->image('foto.jpg', 10, 10);
    $imageBytes = file_get_contents($fakeImage->getRealPath());
    Http::fake([
        'https://oud.rzvg.nl/wp-content/uploads/foto.jpg' => Http::response($imageBytes, 200, ['Content-Type' => 'image/jpeg']),
    ]);

    $item = makeWordpressImportItem([
        'content_html' => '<p><img src="https://oud.rzvg.nl/wp-content/uploads/foto.jpg"></p>',
    ]);
    $media = makeWordpressImportMediaItem($item, ['url' => 'https://oud.rzvg.nl/wp-content/uploads/foto.jpg']);

    $this->actingAs($this->beheerder);

    Livewire::test(WordpressImportBeheer::class)->call('takeOver', $item->id);

    $media->refresh();
    expect($media->media_asset_id)->not->toBeNull()
        ->and($media->download_error)->toBeNull();

    $asset = MediaAsset::findOrFail($media->media_asset_id);
    $item->refresh();
    $page = Page::findOrFail($item->page_id);
    $block = $page->versions()->firstOrFail()->bands()->firstOrFail()->blocks()->firstOrFail();

    expect($block->content['html'])->toContain($asset->displayUrl())
        ->and($block->content['html'])->not->toContain('oud.rzvg.nl');
});

it('behoudt de oude URL en zet een foutmelding als de download mislukt', function () {
    Http::fake([
        'https://oud.rzvg.nl/wp-content/uploads/weg.jpg' => Http::response('', 404),
    ]);

    $item = makeWordpressImportItem([
        'content_html' => '<p><img src="https://oud.rzvg.nl/wp-content/uploads/weg.jpg"></p>',
    ]);
    $media = makeWordpressImportMediaItem($item, ['url' => 'https://oud.rzvg.nl/wp-content/uploads/weg.jpg']);

    $this->actingAs($this->beheerder);

    Livewire::test(WordpressImportBeheer::class)->call('takeOver', $item->id);

    $media->refresh();
    expect($media->media_asset_id)->toBeNull()
        ->and($media->download_error)->not->toBeNull();

    $item->refresh();
    $page = Page::findOrFail($item->page_id);
    $block = $page->versions()->firstOrFail()->bands()->firstOrFail()->blocks()->firstOrFail();

    expect($block->content['html'])->toContain('oud.rzvg.nl/wp-content/uploads/weg.jpg');
});

it('downloadt niet-geselecteerde media niet bij overnemen', function () {
    Http::fake();

    $item = makeWordpressImportItem();
    makeWordpressImportMediaItem($item, ['selected' => false, 'url' => 'https://oud.rzvg.nl/wp-content/uploads/niet.jpg']);

    $this->actingAs($this->beheerder);

    Livewire::test(WordpressImportBeheer::class)->call('takeOver', $item->id);

    Http::assertNothingSent();
});
