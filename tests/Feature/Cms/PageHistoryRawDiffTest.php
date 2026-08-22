<?php

use App\Enums\BandLayout;
use App\Enums\BlockType;
use App\Enums\PageVersionStatus;
use App\Models\Band;
use App\Models\Block;
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

function rawDiffLoginPagesViewer(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $person = Person::create(['first_name' => 'V', 'last_name' => 'Iewer'.uniqid(), 'account_id' => $user->id]);
    PersonPermission::create([
        'person_id' => $person->id,
        'permission_id' => Permission::where('key', 'pages.view')->value('id'),
        'status' => 'active',
    ]);

    return $user;
}

it('toont de rauwe-JSON-tab als een regel-voor-regel diff i.p.v. twee losse tekstblokken', function () {
    $user = rawDiffLoginPagesViewer();
    $template = Template::create(['name' => 'Standaard', 'zones' => [['key' => 'hoofd', 'label' => 'Hoofd']]]);
    $page = Page::create([
        'slug' => 'raw-diff-test',
        'title' => 'Raw diff test',
        'type' => 'content',
        'visibility' => 'publiek',
        'template_id' => $template->id,
    ]);

    $a = PageVersion::create(['page_id' => $page->id, 'version_no' => 1, 'status' => PageVersionStatus::Draft]);
    $bandA = Band::create(['page_version_id' => $a->id, 'zone' => 'hoofd', 'layout' => BandLayout::OneColumn, 'sort_order' => 0]);
    Block::create([
        'band_id' => $bandA->id, 'column_index' => 0, 'sort_order' => 0,
        'type' => BlockType::Text, 'content' => ['html' => 'oude tekst'],
    ]);

    $b = PageVersion::create(['page_id' => $page->id, 'version_no' => 2, 'status' => PageVersionStatus::Draft]);
    $bandB = Band::create(['page_version_id' => $b->id, 'origin_band_id' => $bandA->id, 'zone' => 'hoofd', 'layout' => BandLayout::OneColumn, 'sort_order' => 0]);
    Block::create([
        'band_id' => $bandB->id, 'column_index' => 0, 'sort_order' => 0,
        'type' => BlockType::Text, 'content' => ['html' => 'nieuwe tekst'],
    ]);

    $this->actingAs($user)
        ->get(route('admin.pages.history.diff', ['page' => $page, 'version' => $a, 'other' => $b]))
        ->assertOk()
        ->assertSee('Regels op dezelfde hoogte horen bij elkaar', false)
        ->assertSee('bg-red-50', false)
        ->assertSee('bg-green-50', false)
        ->assertSee('oude tekst', false)
        ->assertSee('nieuwe tekst', false);
});
