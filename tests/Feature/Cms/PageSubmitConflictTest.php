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
use Database\Seeders\ReviewPolicySeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(ReviewPolicySeeder::class);
    $this->template = Template::create([
        'name' => 'Standaard',
        'zones' => [['key' => 'hoofd', 'label' => 'Hoofd']],
    ]);
});

function editorWith(array $keys): array
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $person = Person::create([
        'first_name' => 'R',
        'last_name' => 'Ed'.uniqid(),
        'account_id' => $user->id,
    ]);
    foreach ($keys as $key) {
        PersonPermission::create([
            'person_id' => $person->id,
            'permission_id' => Permission::where('key', $key)->value('id'),
            'status' => 'active',
        ]);
    }

    return [$user, $person];
}

it('redirects to the conflict resolver when submit finds a newer published version', function () {
    [$user, $person] = editorWith(['pages.view', 'pages.update']);

    $page = Page::create(['slug' => 'p', 'title' => 'P', 'template_id' => $this->template->id]);

    // v1: gepubliceerd op 't moment jouw concept vertakt
    $v1 = PageVersion::create(['page_id' => $page->id, 'version_no' => 1, 'status' => PageVersionStatus::Published]);
    $band1 = Band::create(['page_version_id' => $v1->id, 'zone' => 'hoofd', 'layout' => BandLayout::OneColumn, 'sort_order' => 0]);
    $block1 = Block::create(['band_id' => $band1->id, 'column_index' => 0, 'sort_order' => 0, 'type' => BlockType::Text, 'content' => ['html' => '<p>oud</p>']]);
    $page->update(['published_version_id' => $v1->id]);

    // jouw concept vertakt van v1
    $mine = PageVersion::create([
        'page_id' => $page->id, 'version_no' => 2, 'status' => PageVersionStatus::Draft,
        'base_version_id' => $v1->id, 'created_by_person_id' => $person->id,
    ]);
    $myBand = Band::create(['page_version_id' => $mine->id, 'origin_band_id' => $band1->id, 'zone' => 'hoofd', 'layout' => BandLayout::OneColumn, 'sort_order' => 0]);
    Block::create(['band_id' => $myBand->id, 'origin_block_id' => $block1->id, 'column_index' => 0, 'sort_order' => 0, 'type' => BlockType::Text, 'content' => ['html' => '<p>mijn edit</p>']]);

    // Intussen wordt v3 gepubliceerd met een andere edit op hetzelfde blok
    $v3 = PageVersion::create(['page_id' => $page->id, 'version_no' => 3, 'status' => PageVersionStatus::Published]);
    $band3 = Band::create(['page_version_id' => $v3->id, 'origin_band_id' => $band1->id, 'zone' => 'hoofd', 'layout' => BandLayout::OneColumn, 'sort_order' => 0]);
    Block::create(['band_id' => $band3->id, 'origin_block_id' => $block1->id, 'column_index' => 0, 'sort_order' => 0, 'type' => BlockType::Text, 'content' => ['html' => '<p>iemand anders</p>']]);
    $page->update(['published_version_id' => $v3->id]);

    $this->actingAs($user)
        ->post("/beheer/paginas/{$page->id}/versies/{$mine->id}/indienen")
        ->assertRedirect(route('admin.pages.conflict.show', [
            'page' => $page,
            'version' => $mine,
            'other' => $v3,
        ]));
});

it('lets submit proceed normally when there is no newer published version', function () {
    [$user, $person] = editorWith(['pages.view', 'pages.update']);

    $page = Page::create(['slug' => 'p2', 'title' => 'P', 'template_id' => $this->template->id]);
    $v1 = PageVersion::create(['page_id' => $page->id, 'version_no' => 1, 'status' => PageVersionStatus::Published]);
    $page->update(['published_version_id' => $v1->id]);

    $mine = PageVersion::create([
        'page_id' => $page->id, 'version_no' => 2, 'status' => PageVersionStatus::Draft,
        'base_version_id' => $v1->id, 'created_by_person_id' => $person->id,
    ]);

    $this->actingAs($user)
        ->post("/beheer/paginas/{$page->id}/versies/{$mine->id}/indienen")
        ->assertRedirect();

    expect($mine->fresh()->status)->toBe(PageVersionStatus::InReview);
});

it('gives each editor their own draft version', function () {
    [$userA, $personA] = editorWith(['pages.view', 'pages.update']);
    [$userB, $personB] = editorWith(['pages.view', 'pages.update']);

    $page = Page::create(['slug' => 'p3', 'title' => 'P', 'template_id' => $this->template->id]);
    $v1 = PageVersion::create(['page_id' => $page->id, 'version_no' => 1, 'status' => PageVersionStatus::Published]);
    $page->update(['published_version_id' => $v1->id]);

    $this->actingAs($userA)->get("/beheer/paginas/{$page->id}/bewerker")->assertOk();
    $this->actingAs($userB)->get("/beheer/paginas/{$page->id}/bewerker")->assertOk();

    $draftsA = PageVersion::where('page_id', $page->id)->where('created_by_person_id', $personA->id)->count();
    $draftsB = PageVersion::where('page_id', $page->id)->where('created_by_person_id', $personB->id)->count();

    expect($draftsA)->toBe(1)->and($draftsB)->toBe(1);
});
