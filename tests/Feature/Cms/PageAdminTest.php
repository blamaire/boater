<?php

use App\Enums\PageVersionStatus;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Permission;
use App\Models\Person;
use App\Models\PersonPermission;
use App\Models\Template;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->template = Template::create([
        'name' => 'Standaard',
        'zones' => [['key' => 'hoofd', 'label' => 'Hoofd']],
    ]);
});

function loginEditor(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $person = Person::create([
        'first_name' => 'Red',
        'last_name' => 'Acteur',
        'account_id' => $user->id,
    ]);
    foreach (['pages.view', 'pages.create', 'pages.update', 'pages.delete'] as $key) {
        PersonPermission::create([
            'person_id' => $person->id,
            'permission_id' => Permission::where('key', $key)->value('id'),
            'status' => 'active',
        ]);
    }

    return $user;
}

it('requires authentication to view the page index', function () {
    $this->get('/beheer/paginas')->assertRedirect('/login');
});

it('forbids users without pages.view to see the index', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    Person::create([
        'first_name' => 'Jan',
        'last_name' => 'Lid',
        'account_id' => $user->id,
    ]);

    $this->actingAs($user)->get('/beheer/paginas')->assertForbidden();
});

it('allows users with pages.view to see the index', function () {
    $user = loginEditor();

    $this->actingAs($user)
        ->get('/beheer/paginas')
        ->assertOk()
        ->assertSee('Nieuwe pagina');
});

it('creates a new page with an initial draft version', function () {
    $user = loginEditor();

    $response = $this->actingAs($user)->post('/beheer/paginas', [
        'title' => 'Over ons',
        'slug' => 'over-ons',
        'visibility' => 'publiek',
        'parent_id' => null,
        'template_id' => $this->template->id,
    ]);

    $page = Page::query()->where('slug', 'over-ons')->first();
    expect($page)->not->toBeNull()
        ->and($page->title)->toBe('Over ons');

    $version = PageVersion::where('page_id', $page->id)->first();
    expect($version->version_no)->toBe(1)
        ->and($version->status)->toBe(PageVersionStatus::Draft);

    $response->assertRedirect("/beheer/paginas/{$page->id}/bewerker");
});

it('rejects an invalid slug', function () {
    $user = loginEditor();

    $this->actingAs($user)
        ->post('/beheer/paginas', [
            'title' => 'Foute slug',
            'slug' => 'GROTE LETTERS',
            'visibility' => 'publiek',
            'parent_id' => null,
            'template_id' => $this->template->id,
        ])
        ->assertSessionHasErrors('slug');
});

it('enforces unique slug per parent', function () {
    $user = loginEditor();

    Page::create([
        'slug' => 'duplicaat',
        'title' => 'Eerst',
        'template_id' => $this->template->id,
    ]);

    $this->actingAs($user)
        ->post('/beheer/paginas', [
            'title' => 'Tweede',
            'slug' => 'duplicaat',
            'visibility' => 'publiek',
            'parent_id' => null,
            'template_id' => $this->template->id,
        ])
        ->assertSessionHasErrors('slug');
});

it('refuses to delete a system page', function () {
    $user = loginEditor();

    $page = Page::create([
        'slug' => 'home',
        'title' => 'Home',
        'type' => 'systeem',
        'template_id' => $this->template->id,
    ]);

    $this->actingAs($user)
        ->delete('/beheer/paginas/'.$page->id)
        ->assertRedirect('/beheer/paginas');

    expect(Page::find($page->id))->not->toBeNull();
});
