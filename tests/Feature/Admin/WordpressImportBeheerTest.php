<?php

use App\Enums\WordpressContentType;
use App\Enums\WordpressImportStatus;
use App\Livewire\Admin\WordpressImportBeheer;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use App\Models\WordpressImportItem;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TemplateSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(TemplateSeeder::class);

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

it('vereist wordpress_import.manage permissie om de pagina te bezoeken', function () {
    $lid = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($lid)->get('/beheer/wordpress-import')->assertForbidden();
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

it('sorteert op kolom en wisselt van richting bij nogmaals klikken', function () {
    $this->actingAs($this->beheerder);

    Livewire::test(WordpressImportBeheer::class)
        ->assertSet('sortField', 'wordpress_published_at')
        ->assertSet('sortDirection', 'desc')
        ->call('sortBy', 'title')
        ->assertSet('sortField', 'title')
        ->assertSet('sortDirection', 'asc')
        ->call('sortBy', 'title')
        ->assertSet('sortField', 'title')
        ->assertSet('sortDirection', 'desc')
        ->call('sortBy', 'status')
        ->assertSet('sortField', 'status')
        ->assertSet('sortDirection', 'asc');
});

it('negeert een onbekende sorteerkolom', function () {
    $this->actingAs($this->beheerder);

    Livewire::test(WordpressImportBeheer::class)
        ->call('sortBy', 'onbestaand_veld')
        ->assertSet('sortField', 'wordpress_published_at')
        ->assertSet('sortDirection', 'desc');
});
