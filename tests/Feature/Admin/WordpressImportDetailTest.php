<?php

use App\Enums\MediaType;
use App\Enums\PageType;
use App\Enums\PageVersionStatus;
use App\Enums\PageVisibility;
use App\Enums\WordpressContentType;
use App\Enums\WordpressImportStatus;
use App\Livewire\Admin\WordpressImportDetail;
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

function newWordpressImportItem(array $overrides = []): WordpressImportItem
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

function newWordpressImportMediaItem(WordpressImportItem $item, array $overrides = []): WordpressImportMediaItem
{
    return WordpressImportMediaItem::create(array_merge([
        'wordpress_import_item_id' => $item->id,
        'wordpress_id' => random_int(1000, 999999),
        'title' => 'foto.jpg',
        'url' => 'https://oud.rzvg.nl/wp-content/uploads/foto.jpg',
        'mime_type' => 'image/jpeg',
        'selected' => null,
    ], $overrides));
}

it('vereist wordpress_import.manage permissie om de detailpagina te bezoeken', function () {
    $item = newWordpressImportItem();
    $lid = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($lid)->get("/beheer/wordpress-import/{$item->id}")->assertForbidden();
});

it('vereist wordpress_import.manage permissie om acties uit te voeren', function () {
    $item = newWordpressImportItem();
    $lid = User::factory()->create(['email_verified_at' => now()]);
    Person::create(['first_name' => 'L', 'last_name' => 'Id', 'account_id' => $lid->id]);

    $this->actingAs($lid);

    Livewire::test(WordpressImportDetail::class, ['item' => $item])
        ->call('archive', false)
        ->assertForbidden();
});

it('toont een gesaneerde voorvertoning van de content', function () {
    $item = newWordpressImportItem([
        'content_html' => '<p>Veilige tekst.</p><script>alert(1)</script>',
    ]);

    $this->actingAs($this->beheerder)
        ->get("/beheer/wordpress-import/{$item->id}")
        ->assertOk()
        ->assertSee('Veilige tekst.', false)
        ->assertDontSee('<script>', false);
});

it('neemt een nieuw item over als CMS-pagina in concept', function () {
    $item = newWordpressImportItem(['title' => 'Over ons', 'slug' => 'over-ons', 'content_html' => '<p>Inhoud.</p>']);

    $this->actingAs($this->beheerder);

    $component = Livewire::test(WordpressImportDetail::class, ['item' => $item])
        ->call('accept', false);

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

it('weigert een tweede accept op een al overgenomen item', function () {
    $item = newWordpressImportItem();

    $this->actingAs($this->beheerder);

    Livewire::test(WordpressImportDetail::class, ['item' => $item])->call('accept', false);

    $pageCountAfterFirst = Page::count();

    $item->refresh();
    Livewire::test(WordpressImportDetail::class, ['item' => $item])
        ->call('accept', false)
        ->assertSet('errorMessage', fn ($value) => $value !== null);

    expect(Page::count())->toBe($pageCountAfterFirst);
});

it('archiveert een nieuw item zonder een pagina aan te maken en blijft op de pagina', function () {
    $item = newWordpressImportItem();
    $pageCountBefore = Page::count();

    $this->actingAs($this->beheerder);

    Livewire::test(WordpressImportDetail::class, ['item' => $item])
        ->call('archive', false)
        ->assertSet('statusMessage', fn ($value) => $value !== null)
        ->assertNoRedirect();

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

    $item = newWordpressImportItem(['slug' => 'over-ons', 'title' => 'Over ons']);

    $this->actingAs($this->beheerder);

    Livewire::test(WordpressImportDetail::class, ['item' => $item])->call('accept', false);

    $item->refresh();
    $page = Page::findOrFail($item->page_id);
    expect($page->slug)->toBe('over-ons-2');
});

it('zet een gearchiveerd item terug naar nieuw', function () {
    $item = newWordpressImportItem(['status' => WordpressImportStatus::Archived]);

    $this->actingAs($this->beheerder);

    Livewire::test(WordpressImportDetail::class, ['item' => $item])->call('restoreToNew');

    expect($item->refresh()->status)->toBe(WordpressImportStatus::New);
});

it('een bijlage start onbeslist en kan expliciet overgenomen of afgewezen worden', function () {
    $item = newWordpressImportItem();
    $media = newWordpressImportMediaItem($item);

    expect($media->selected)->toBeNull();

    $this->actingAs($this->beheerder);

    Livewire::test(WordpressImportDetail::class, ['item' => $item])->call('decideMedia', $media->id, true);
    expect($media->refresh()->selected)->toBeTrue();

    Livewire::test(WordpressImportDetail::class, ['item' => $item])->call('decideMedia', $media->id, false);
    expect($media->refresh()->selected)->toBeFalse();
});

it('weigert het beslissen over media op een al overgenomen item', function () {
    $item = newWordpressImportItem(['status' => WordpressImportStatus::Imported]);
    $media = newWordpressImportMediaItem($item);

    $this->actingAs($this->beheerder);

    Livewire::test(WordpressImportDetail::class, ['item' => $item])
        ->call('decideMedia', $media->id, true)
        ->assertForbidden();
});

it('accepteert of weigert alle nog niet gedownloade media in één keer', function () {
    $item = newWordpressImportItem();
    $een = newWordpressImportMediaItem($item);
    $twee = newWordpressImportMediaItem($item);
    $alGedownload = newWordpressImportMediaItem($item, ['selected' => true, 'media_asset_id' => null]);
    $asset = MediaAsset::create([
        'disk' => 'media', 'path' => 'assets/test.jpg', 'original_name' => 'test.jpg',
        'mime_type' => 'image/jpeg', 'type' => MediaType::Image, 'file_size' => 1,
        'visibility' => PageVisibility::Public,
    ]);
    $alGedownload->update(['media_asset_id' => $asset->id]);

    $this->actingAs($this->beheerder);

    Livewire::test(WordpressImportDetail::class, ['item' => $item])->call('acceptAllMedia');
    expect($een->refresh()->selected)->toBeTrue()
        ->and($twee->refresh()->selected)->toBeTrue()
        ->and($alGedownload->refresh()->selected)->toBeTrue();

    Livewire::test(WordpressImportDetail::class, ['item' => $item])->call('rejectAllMedia');
    expect($een->refresh()->selected)->toBeFalse()
        ->and($twee->refresh()->selected)->toBeFalse()
        ->and($alGedownload->refresh()->selected)->toBeTrue();
});

it('downloadt geselecteerde media bij overnemen en herschrijft de content-URL', function () {
    $fakeImage = UploadedFile::fake()->image('foto.jpg', 10, 10);
    $imageBytes = file_get_contents($fakeImage->getRealPath());
    Http::fake([
        'https://oud.rzvg.nl/wp-content/uploads/foto.jpg' => Http::response($imageBytes, 200, ['Content-Type' => 'image/jpeg']),
    ]);

    $item = newWordpressImportItem([
        'content_html' => '<p><img src="https://oud.rzvg.nl/wp-content/uploads/foto.jpg"></p>',
    ]);
    $media = newWordpressImportMediaItem($item, ['url' => 'https://oud.rzvg.nl/wp-content/uploads/foto.jpg', 'selected' => true]);

    $this->actingAs($this->beheerder);

    Livewire::test(WordpressImportDetail::class, ['item' => $item])->call('accept', false);

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

    $item = newWordpressImportItem([
        'content_html' => '<p><img src="https://oud.rzvg.nl/wp-content/uploads/weg.jpg"></p>',
    ]);
    $media = newWordpressImportMediaItem($item, ['url' => 'https://oud.rzvg.nl/wp-content/uploads/weg.jpg', 'selected' => true]);

    $this->actingAs($this->beheerder);

    Livewire::test(WordpressImportDetail::class, ['item' => $item])->call('accept', false);

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

    $item = newWordpressImportItem();
    newWordpressImportMediaItem($item, ['selected' => false, 'url' => 'https://oud.rzvg.nl/wp-content/uploads/niet.jpg']);

    $this->actingAs($this->beheerder);

    Livewire::test(WordpressImportDetail::class, ['item' => $item])->call('accept', false);

    Http::assertNothingSent();
});

it('accepteren en volgende springt naar het eerstvolgende nieuwe item in de huidige sortering', function () {
    $a = newWordpressImportItem(['title' => 'Aaa item']);
    $b = newWordpressImportItem(['title' => 'Bbb item']);
    $c = newWordpressImportItem(['title' => 'Ccc item']);

    $this->actingAs($this->beheerder);

    Livewire::test(WordpressImportDetail::class, ['item' => $a])
        ->set('sortField', 'title')
        ->set('sortDirection', 'asc')
        ->call('accept', true)
        ->assertRedirect(route('admin.wordpress-import.show', [
            'item' => $b->id, 'sort' => 'title', 'direction' => 'asc', 'filterType' => '', 'filterStatus' => '',
        ]));

    expect($a->refresh()->status)->toBe(WordpressImportStatus::Imported)
        ->and($b->refresh()->status)->toBe(WordpressImportStatus::New)
        ->and($c->refresh()->status)->toBe(WordpressImportStatus::New);
});

it('archiveren en volgende respecteert de actieve type-filter', function () {
    $pagina1 = newWordpressImportItem(['title' => 'Aaa pagina', 'wordpress_type' => WordpressContentType::Page]);
    $bericht = newWordpressImportItem(['title' => 'Bbb bericht', 'wordpress_type' => WordpressContentType::Post]);
    $pagina2 = newWordpressImportItem(['title' => 'Ccc pagina', 'wordpress_type' => WordpressContentType::Page]);

    $this->actingAs($this->beheerder);

    Livewire::test(WordpressImportDetail::class, ['item' => $pagina1])
        ->set('sortField', 'title')
        ->set('sortDirection', 'asc')
        ->set('filterType', WordpressContentType::Page->value)
        ->call('archive', true)
        ->assertRedirect(route('admin.wordpress-import.show', [
            'item' => $pagina2->id, 'sort' => 'title', 'direction' => 'asc', 'filterType' => WordpressContentType::Page->value, 'filterStatus' => '',
        ]));

    expect($pagina1->refresh()->status)->toBe(WordpressImportStatus::Archived)
        ->and($bericht->refresh()->status)->toBe(WordpressImportStatus::New)
        ->and($pagina2->refresh()->status)->toBe(WordpressImportStatus::New);
});

it('toont de positie van het item binnen de actieve sortering en filter', function () {
    $pagina = newWordpressImportItem(['title' => 'Aaa pagina', 'wordpress_type' => WordpressContentType::Page]);
    newWordpressImportItem(['title' => 'Bbb bericht', 'wordpress_type' => WordpressContentType::Post]);
    newWordpressImportItem(['title' => 'Ccc pagina', 'wordpress_type' => WordpressContentType::Page]);

    $this->actingAs($this->beheerder);

    Livewire::test(WordpressImportDetail::class, ['item' => $pagina])
        ->set('sortField', 'title')
        ->set('sortDirection', 'asc')
        ->set('filterType', WordpressContentType::Page->value)
        ->assertSet('errorMessage', null)
        ->assertSee('Item 1 van 2');
});

it('gaat terug naar het overzicht met een melding als er geen volgend item meer is', function () {
    $item = newWordpressImportItem();

    $this->actingAs($this->beheerder);

    Livewire::test(WordpressImportDetail::class, ['item' => $item])
        ->call('accept', true)
        ->assertRedirect(route('admin.wordpress-import.index'));

    expect(session('wordpress_import_status'))->not->toBeNull();
});
