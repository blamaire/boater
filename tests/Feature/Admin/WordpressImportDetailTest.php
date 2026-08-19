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
        ->and($page->visibility)->toBe(PageVisibility::Restricted)
        ->and($page->parent_id)->toBeNull()
        ->and($page->template_id)->toBe(Template::where('name', 'Standaard')->value('id'))
        ->and($page->published_version_id)->toBeNull();

    $version = $page->versions()->firstOrFail();
    expect($version->version_no)->toBe(1)
        ->and($version->status)->toBe(PageVersionStatus::Draft);

    $band = $version->bands()->firstOrFail();
    expect($band->zone)->toBe('hoofd');

    $heading = $band->blocks()->firstOrFail();
    expect($heading->type->value)->toBe('kop')
        ->and($heading->content['level'])->toBe(1)
        ->and($heading->content['text'])->toBe('Over ons');

    $block = $band->blocks()->where('type', 'tekst')->firstOrFail();
    expect($block->content['html'])->toBe('<p>Inhoud.</p>');

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

it('kan de zichtbaarheid vóór overnemen op publiek zetten', function () {
    $item = newWordpressImportItem();

    $this->actingAs($this->beheerder);

    Livewire::test(WordpressImportDetail::class, ['item' => $item])
        ->set('visibility', PageVisibility::Public->value)
        ->call('accept', false);

    $page = Page::findOrFail($item->refresh()->page_id);
    expect($page->visibility)->toBe(PageVisibility::Public);
});

it('stelt de bovenliggende pagina automatisch in als de oude ouder al is overgenomen', function () {
    $ouderItem = newWordpressImportItem(['wordpress_id' => 100, 'title' => 'Activiteiten']);
    $this->actingAs($this->beheerder);
    Livewire::test(WordpressImportDetail::class, ['item' => $ouderItem])->call('accept', false);
    $ouderPagina = Page::findOrFail($ouderItem->refresh()->page_id);

    $kindItem = newWordpressImportItem([
        'wordpress_id' => 101,
        'wordpress_parent_id' => 100,
        'wordpress_type' => WordpressContentType::Page,
        'title' => 'Wedstrijden',
        'slug' => 'wedstrijden',
    ]);

    $component = Livewire::test(WordpressImportDetail::class, ['item' => $kindItem])
        ->assertSet('parentId', $ouderPagina->id)
        ->assertSee('Activiteiten')
        ->call('accept', false);

    $page = Page::findOrFail($kindItem->refresh()->page_id);
    expect($page->parent_id)->toBe($ouderPagina->id);
});

it('toont de volledige oude paginahiërarchie met statusiconen, root eerst', function () {
    $grootouder = newWordpressImportItem(['wordpress_id' => 10, 'title' => 'Vereniging', 'status' => WordpressImportStatus::Archived]);
    $ouder = newWordpressImportItem(['wordpress_id' => 100, 'wordpress_parent_id' => 10, 'title' => 'Activiteiten']);

    $kindItem = newWordpressImportItem([
        'wordpress_id' => 101,
        'wordpress_parent_id' => 100,
        'wordpress_type' => WordpressContentType::Page,
        'title' => 'Wedstrijden',
    ]);

    $this->actingAs($this->beheerder);

    Livewire::test(WordpressImportDetail::class, ['item' => $kindItem])
        ->assertSeeInOrder(['Vereniging', 'Activiteiten'])
        ->assertSee(route('admin.wordpress-import.show', [
            'item' => $grootouder->id, 'sort' => 'wordpress_published_at', 'direction' => 'desc', 'filterType' => '', 'filterStatus' => '',
        ]))
        ->assertSee(route('admin.wordpress-import.show', [
            'item' => $ouder->id, 'sort' => 'wordpress_published_at', 'direction' => 'desc', 'filterType' => '', 'filterStatus' => '',
        ]));
});

it('past de ouder-suggestie en -boom niet toe op berichten', function () {
    newWordpressImportItem(['wordpress_id' => 100, 'title' => 'Activiteiten']);

    $bericht = newWordpressImportItem([
        'wordpress_id' => 202,
        'wordpress_parent_id' => 100,
        'wordpress_type' => WordpressContentType::Post,
        'title' => 'Wedstrijdverslag',
    ]);

    $this->actingAs($this->beheerder);

    Livewire::test(WordpressImportDetail::class, ['item' => $bericht])
        ->assertSet('parentId', null)
        ->assertDontSee('Activiteiten');
});

it('scoopt slug-uniciteit per bovenliggende pagina, niet globaal', function () {
    $ouder1 = Page::create([
        'slug' => 'ouder-1', 'title' => 'Ouder 1', 'type' => PageType::Content,
        'visibility' => PageVisibility::Restricted, 'parent_id' => null, 'template_id' => Template::where('name', 'Standaard')->value('id'),
    ]);
    $ouder2 = Page::create([
        'slug' => 'ouder-2', 'title' => 'Ouder 2', 'type' => PageType::Content,
        'visibility' => PageVisibility::Restricted, 'parent_id' => null, 'template_id' => Template::where('name', 'Standaard')->value('id'),
    ]);

    $item1 = newWordpressImportItem(['wordpress_id' => 301, 'slug' => 'contact', 'title' => 'Contact 1']);
    $item2 = newWordpressImportItem(['wordpress_id' => 302, 'slug' => 'contact', 'title' => 'Contact 2']);

    $this->actingAs($this->beheerder);

    Livewire::test(WordpressImportDetail::class, ['item' => $item1])->set('parentId', $ouder1->id)->call('accept', false);
    Livewire::test(WordpressImportDetail::class, ['item' => $item2])->set('parentId', $ouder2->id)->call('accept', false);

    $pagina1 = Page::findOrFail($item1->refresh()->page_id);
    $pagina2 = Page::findOrFail($item2->refresh()->page_id);

    expect($pagina1->slug)->toBe('contact')
        ->and($pagina2->slug)->toBe('contact');
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

it('staat beslissen wel toe als het bekeken item nog Nieuw is, ook als de bijlage formeel aan een ander (al overgenomen) item hangt', function () {
    $anderItem = newWordpressImportItem(['wordpress_id' => 999, 'status' => WordpressImportStatus::Imported]);
    $media = newWordpressImportMediaItem($anderItem, [
        'url' => 'https://oud.rzvg.nl/wp-content/uploads/hergebruikt.jpg',
    ]);

    $item = newWordpressImportItem([
        'content_html' => '<p><img src="https://oud.rzvg.nl/wp-content/uploads/hergebruikt.jpg"></p>',
    ]);

    $this->actingAs($this->beheerder);

    Livewire::test(WordpressImportDetail::class, ['item' => $item])
        ->call('decideMedia', $media->id, true);

    expect($media->refresh()->selected)->toBeTrue();
});

it('accepteert of weigert alle nog niet gedownloade media in één keer', function () {
    $item = newWordpressImportItem([
        'content_html' => '<p><img src="https://oud.rzvg.nl/wp-content/uploads/een.jpg">'
            .'<img src="https://oud.rzvg.nl/wp-content/uploads/twee-300x200.jpg"></p>',
    ]);
    $een = newWordpressImportMediaItem($item, ['url' => 'https://oud.rzvg.nl/wp-content/uploads/een.jpg']);
    $twee = newWordpressImportMediaItem($item, ['url' => 'https://oud.rzvg.nl/wp-content/uploads/twee.jpg']);
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

it('herkent media die alleen via een geschaalde bestandsnaamvariant in de content voorkomt', function () {
    $item = newWordpressImportItem([
        'content_html' => '<p><img src="https://oud.rzvg.nl/wp-content/uploads/IMG_2293-296x222.jpg"></p>',
    ]);
    $media = newWordpressImportMediaItem($item, [
        'title' => 'IMG_2293.jpg',
        'url' => 'https://oud.rzvg.nl/wp-content/uploads/IMG_2293.jpg',
    ]);

    $this->actingAs($this->beheerder);

    Livewire::test(WordpressImportDetail::class, ['item' => $item])
        ->assertSee($media->title);
});

it('toont een bijlage die formeel aan een ander item gekoppeld is als ze hier wel in de content staat', function () {
    $anderItem = newWordpressImportItem(['wordpress_id' => 999, 'title' => 'Ander item']);
    $media = newWordpressImportMediaItem($anderItem, [
        'title' => 'hergebruikt.jpg',
        'url' => 'https://oud.rzvg.nl/wp-content/uploads/hergebruikt.jpg',
    ]);

    $item = newWordpressImportItem([
        'content_html' => '<p><img src="https://oud.rzvg.nl/wp-content/uploads/hergebruikt.jpg"></p>',
    ]);

    $this->actingAs($this->beheerder);

    Livewire::test(WordpressImportDetail::class, ['item' => $item])
        ->assertSee('hergebruikt.jpg')
        ->call('acceptAllMedia');

    expect($media->refresh()->selected)->toBeTrue();
});

it('herkent WordPress\' -scaled-achtervoegsel op de opgeslagen bestandsnaam', function () {
    $item = newWordpressImportItem([
        'content_html' => '<p><img src="https://oud.rzvg.nl/wp-content/uploads/IMG_2293-296x222.jpg"></p>',
    ]);
    $media = newWordpressImportMediaItem($item, [
        'title' => 'IMG_2293-scaled.jpg',
        'url' => 'https://oud.rzvg.nl/wp-content/uploads/IMG_2293-scaled.jpg',
    ]);

    $this->actingAs($this->beheerder);

    Livewire::test(WordpressImportDetail::class, ['item' => $item])
        ->assertSee($media->title);
});

it('markeert afbeeldingen in de voorvertoning inline met hun beslisstatus', function () {
    $item = newWordpressImportItem([
        'content_html' => '<p><img src="https://oud.rzvg.nl/wp-content/uploads/onbeslist.jpg">'
            .'<img src="https://oud.rzvg.nl/wp-content/uploads/afgewezen.jpg"></p>',
    ]);
    newWordpressImportMediaItem($item, ['url' => 'https://oud.rzvg.nl/wp-content/uploads/onbeslist.jpg', 'selected' => null]);
    newWordpressImportMediaItem($item, ['url' => 'https://oud.rzvg.nl/wp-content/uploads/afgewezen.jpg', 'selected' => false]);

    $this->actingAs($this->beheerder)
        ->get("/beheer/wordpress-import/{$item->id}")
        ->assertOk()
        ->assertSee('Nog geen besluit')
        ->assertSee('Niet overgenomen');
});

it('laat de keuze ook inline vanuit de voorvertoning maken zonder de omringende opmaak te wijzigen', function () {
    $item = newWordpressImportItem([
        'content_html' => '<p>Voor.<img src="https://oud.rzvg.nl/wp-content/uploads/inline.jpg">Na.</p>',
    ]);
    $media = newWordpressImportMediaItem($item, ['url' => 'https://oud.rzvg.nl/wp-content/uploads/inline.jpg']);

    $this->actingAs($this->beheerder);

    $component = Livewire::test(WordpressImportDetail::class, ['item' => $item]);

    // Voor/na-tekst blijft in dezelfde <p>, geen block-element ertussen.
    $component->assertSeeHtml('Voor.<span');
    $component->assertSeeHtml('wire:click="decideMedia('.$media->id.', true)"');

    $component->call('decideMedia', $media->id, true);

    expect($media->refresh()->selected)->toBeTrue();
});

it('verwijdert een omwikkelende WordPress-link rond een afbeelding, zodat de knoppen niet in die link zitten', function () {
    $item = newWordpressImportItem([
        'content_html' => '<p><a href="https://oud.rzvg.nl/wp-content/uploads/omwikkeld-full.jpg">'
            .'<img src="https://oud.rzvg.nl/wp-content/uploads/omwikkeld-300x200.jpg"></a></p>',
    ]);
    $media = newWordpressImportMediaItem($item, ['url' => 'https://oud.rzvg.nl/wp-content/uploads/omwikkeld.jpg']);

    $this->actingAs($this->beheerder);

    Livewire::test(WordpressImportDetail::class, ['item' => $item])
        ->assertDontSeeHtml('href="https://oud.rzvg.nl/wp-content/uploads/omwikkeld-full.jpg"')
        ->assertSeeHtml('wire:click="decideMedia('.$media->id.', true)"');
});

it('toont bijlagen die nergens in de content voorkomen niet en laat ze door bulkacties ongemoeid', function () {
    $item = newWordpressImportItem([
        'content_html' => '<p>Geen enkele afbeelding hier.</p>',
    ]);
    $ongebruikt = newWordpressImportMediaItem($item, [
        'title' => 'nooit-gebruikt.jpg',
        'url' => 'https://oud.rzvg.nl/wp-content/uploads/nooit-gebruikt.jpg',
    ]);

    $this->actingAs($this->beheerder);

    Livewire::test(WordpressImportDetail::class, ['item' => $item])
        ->assertDontSee('nooit-gebruikt.jpg')
        ->call('acceptAllMedia');

    expect($ongebruikt->refresh()->selected)->toBeNull();
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
    $block = $page->versions()->firstOrFail()->bands()->firstOrFail()->blocks()->where('type', 'tekst')->firstOrFail();

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
    $block = $page->versions()->firstOrFail()->bands()->firstOrFail()->blocks()->where('type', 'tekst')->firstOrFail();

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
