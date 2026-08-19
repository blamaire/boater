<?php

use App\Enums\MediaType;
use App\Enums\PageVisibility;
use App\Enums\WordpressContentType;
use App\Enums\WordpressImportStatus;
use App\Livewire\Admin\WordpressImportMediaOverzicht;
use App\Models\MediaAsset;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use App\Models\WordpressImportItem;
use App\Models\WordpressImportMediaItem;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TemplateSeeder;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(TemplateSeeder::class);

    $this->beheerder = User::factory()->create(['email_verified_at' => now()]);
    Person::create(['first_name' => 'B', 'last_name' => 'Heer', 'account_id' => $this->beheerder->id])
        ->roles()->attach(Role::query()->where('name', 'Beheerder')->value('id'));
});

function overzichtItem(string $title): WordpressImportItem
{
    return WordpressImportItem::create([
        'wordpress_id' => random_int(1000, 999999),
        'wordpress_type' => WordpressContentType::Page,
        'title' => $title,
        'slug' => Str::slug($title),
        'content_html' => '<p>Inhoud.</p>',
        'status' => WordpressImportStatus::New,
        'raw_meta' => ['wp_status' => 'publish', 'categories' => [], 'tags' => []],
    ]);
}

it('vereist wordpress_import.manage permissie', function () {
    $lid = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($lid)->get('/beheer/wordpress-import/media')->assertForbidden();
});

it('toont de status van elke bijlage en filtert daarop', function () {
    $item = overzichtItem('Roeien');

    $asset = MediaAsset::create([
        'disk' => 'media', 'path' => 'assets/test.jpg', 'original_name' => 'test.jpg',
        'mime_type' => 'image/jpeg', 'type' => MediaType::Image, 'file_size' => 1,
        'visibility' => PageVisibility::Public,
    ]);

    WordpressImportMediaItem::create([
        'wordpress_import_item_id' => $item->id, 'wordpress_id' => 1, 'title' => 'overgenomen.jpg',
        'url' => 'https://oud.rzvg.nl/overgenomen.jpg', 'selected' => true, 'media_asset_id' => $asset->id,
    ]);
    WordpressImportMediaItem::create([
        'wordpress_import_item_id' => $item->id, 'wordpress_id' => 2, 'title' => 'mislukt.jpg',
        'url' => 'https://oud.rzvg.nl/mislukt.jpg', 'selected' => true, 'download_error' => 'HTTP 404',
    ]);
    WordpressImportMediaItem::create([
        'wordpress_import_item_id' => $item->id, 'wordpress_id' => 3, 'title' => 'afgewezen.jpg',
        'url' => 'https://oud.rzvg.nl/afgewezen.jpg', 'selected' => false,
    ]);
    WordpressImportMediaItem::create([
        'wordpress_import_item_id' => $item->id, 'wordpress_id' => 4, 'title' => 'onbeslist.jpg',
        'url' => 'https://oud.rzvg.nl/onbeslist.jpg', 'selected' => null,
    ]);

    $this->actingAs($this->beheerder);

    Livewire::test(WordpressImportMediaOverzicht::class)
        ->assertSee('overgenomen.jpg')
        ->assertSee('mislukt.jpg')
        ->assertSee('afgewezen.jpg')
        ->assertSee('onbeslist.jpg')
        ->assertSee('Roeien')
        ->set('filterStatus', 'overgenomen')
        ->assertSee('overgenomen.jpg')
        ->assertDontSee('mislukt.jpg')
        ->assertDontSee('afgewezen.jpg')
        ->assertDontSee('onbeslist.jpg')
        ->set('filterStatus', 'mislukt')
        ->assertSee('mislukt.jpg')
        ->assertDontSee('overgenomen.jpg')
        ->set('filterStatus', 'niet_overgenomen')
        ->assertSee('afgewezen.jpg')
        ->assertDontSee('mislukt.jpg')
        ->set('filterStatus', 'nieuw')
        ->assertSee('onbeslist.jpg')
        ->assertDontSee('afgewezen.jpg');
});

it('linkt naar de detailpagina van het bovenliggende item', function () {
    $item = overzichtItem('Roeien');
    WordpressImportMediaItem::create([
        'wordpress_import_item_id' => $item->id, 'wordpress_id' => 1, 'title' => 'foto.jpg',
        'url' => 'https://oud.rzvg.nl/foto.jpg',
    ]);

    $this->actingAs($this->beheerder);

    Livewire::test(WordpressImportMediaOverzicht::class)
        ->assertSee(route('admin.wordpress-import.show', ['item' => $item->id]));
});
