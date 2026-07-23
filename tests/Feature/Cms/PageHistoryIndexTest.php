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
});

function loginPagesViewer(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $person = Person::create(['first_name' => 'V', 'last_name' => 'Iewer', 'account_id' => $user->id]);
    PersonPermission::create([
        'person_id' => $person->id,
        'permission_id' => Permission::where('key', 'pages.view')->value('id'),
        'status' => 'active',
    ]);

    return $user;
}

it('toont een knop om twee geselecteerde versies te vergelijken', function () {
    $user = loginPagesViewer();
    $template = Template::create(['name' => 'Standaard', 'zones' => [['key' => 'hoofd', 'label' => 'Hoofd']]]);
    $page = Page::create([
        'slug' => 'historie-test',
        'title' => 'Historie test',
        'type' => 'content',
        'visibility' => 'publiek',
        'template_id' => $template->id,
    ]);
    PageVersion::create(['page_id' => $page->id, 'version_no' => 1, 'status' => PageVersionStatus::Draft]);
    PageVersion::create(['page_id' => $page->id, 'version_no' => 2, 'status' => PageVersionStatus::Draft]);

    // Vóór de fix bestond deze knop niet — de enige manier om de diff op te
    // roepen was een kapotte Alpine-watcher die alleen op wijzigingen van
    // radio A reageerde, nooit op radio B.
    $this->actingAs($user)
        ->get(route('admin.pages.history', $page))
        ->assertOk()
        ->assertSee('Vergelijk geselecteerde versies')
        ->assertSee('a === b', false);
});
