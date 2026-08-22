<?php

use App\Enums\BandLayout;
use App\Enums\BlockType;
use App\Enums\PageVersionStatus;
use App\Models\Band;
use App\Models\Block;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Permission;
use App\Models\Person;
use App\Models\PersonPermission;
use App\Models\Template;
use App\Models\User;
use App\Services\Cms\PageVersionCloner;
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

function clonerPersonWith(array $keys): array
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $person = Person::create([
        'first_name' => 'C',
        'last_name' => 'Loner'.uniqid(),
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

it('neemt meta-velden mee bij het aanmaken van een nieuwe conceptversie vanaf de gepubliceerde versie', function () {
    [$user] = clonerPersonWith(['pages.view', 'pages.update']);

    $page = Page::create(['slug' => 'p', 'title' => 'P', 'template_id' => $this->template->id]);
    $asset = MediaAsset::create([
        'disk' => 'public',
        'path' => 'og.jpg',
        'original_name' => 'og.jpg',
        'mime_type' => 'image/jpeg',
        'type' => 'afbeelding',
        'file_size' => 100,
        'visibility' => 'publiek',
    ]);

    $published = PageVersion::create([
        'page_id' => $page->id,
        'version_no' => 1,
        'status' => PageVersionStatus::Published,
        'meta_description' => 'Een omschrijving',
        'og_title' => 'OG titel',
        'og_description' => 'OG omschrijving',
        'og_image_media_asset_id' => $asset->id,
    ]);
    $page->update(['published_version_id' => $published->id]);

    $this->actingAs($user)->get("/beheer/paginas/{$page->id}/bewerker")->assertOk();

    $draft = PageVersion::where('page_id', $page->id)->where('status', PageVersionStatus::Draft)->firstOrFail();

    expect($draft->meta_description)->toBe('Een omschrijving')
        ->and($draft->og_title)->toBe('OG titel')
        ->and($draft->og_description)->toBe('OG omschrijving')
        ->and($draft->og_image_media_asset_id)->toBe($asset->id);
});

it('neemt meta-velden mee bij het herstellen van een oudere versie vanuit de historie', function () {
    [$user] = clonerPersonWith(['pages.view', 'pages.update']);

    $page = Page::create(['slug' => 'p2', 'title' => 'P', 'template_id' => $this->template->id]);

    $old = PageVersion::create([
        'page_id' => $page->id,
        'version_no' => 1,
        'status' => PageVersionStatus::Archived,
        'meta_description' => 'Oude omschrijving',
        'og_title' => 'Oude OG titel',
    ]);

    $this->actingAs($user)
        ->post("/beheer/paginas/{$page->id}/historie/{$old->id}/herstellen")
        ->assertRedirect(route('admin.pages.editor', $page));

    $restored = PageVersion::where('page_id', $page->id)->where('version_no', 2)->firstOrFail();

    expect($restored->meta_description)->toBe('Oude omschrijving')
        ->and($restored->og_title)->toBe('Oude OG titel');
});

it('kopieert een versie zonder meta-velden naar een concept met null-velden, zonder te crashen', function () {
    $page = Page::create(['slug' => 'p3', 'title' => 'P', 'template_id' => $this->template->id]);

    $source = PageVersion::create([
        'page_id' => $page->id,
        'version_no' => 1,
        'status' => PageVersionStatus::Draft,
    ]);
    $band = Band::create([
        'page_version_id' => $source->id,
        'zone' => 'hoofd',
        'layout' => BandLayout::OneColumn,
        'sort_order' => 0,
    ]);
    Block::create([
        'band_id' => $band->id,
        'column_index' => 0,
        'sort_order' => 0,
        'type' => BlockType::Text,
        'content' => ['html' => '<p>a</p>'],
    ]);

    $target = PageVersion::create([
        'page_id' => $page->id,
        'version_no' => 2,
        'status' => PageVersionStatus::Draft,
    ]);

    app(PageVersionCloner::class)->clone($source, $target);

    $target->refresh();
    expect($target->meta_description)->toBeNull()
        ->and($target->og_title)->toBeNull()
        ->and($target->og_description)->toBeNull()
        ->and($target->og_image_media_asset_id)->toBeNull()
        ->and($target->bands()->with('blocks')->first()->blocks)->toHaveCount(1);
});
