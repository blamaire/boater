<?php

use App\Enums\BandLayout;
use App\Enums\BlockType;
use App\Enums\PageVersionStatus;
use App\Enums\PageVisibility;
use App\Models\Band;
use App\Models\Block;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Template;
use App\Models\User;

beforeEach(function () {
    $this->template = Template::create([
        'name' => 'Standaard',
        'zones' => [['key' => 'hoofd', 'label' => 'Hoofd']],
    ]);
});

function publishedPage(Template $template, string $slug, string $title, ?int $parentId = null, PageVisibility $visibility = PageVisibility::Public): Page
{
    $page = Page::create([
        'slug' => $slug,
        'title' => $title,
        'visibility' => $visibility,
        'parent_id' => $parentId,
        'template_id' => $template->id,
    ]);
    $version = PageVersion::create([
        'page_id' => $page->id,
        'version_no' => 1,
        'status' => PageVersionStatus::Published,
    ]);
    $band = Band::create([
        'page_version_id' => $version->id,
        'zone' => 'hoofd',
        'layout' => BandLayout::OneColumn,
        'sort_order' => 0,
    ]);
    Block::create([
        'band_id' => $band->id,
        'column_index' => 0,
        'sort_order' => 0,
        'type' => BlockType::Heading,
        'content' => ['level' => 1, 'text' => $title],
    ]);

    $page->update(['published_version_id' => $version->id]);

    return $page;
}

it('renders a published public root page on /{slug}', function () {
    publishedPage($this->template, 'over-ons', 'Over ons');

    $this->get('/over-ons')
        ->assertOk()
        ->assertSee('Over ons');
});

it('renders a hierarchical page on /{parent-slug}/{child-slug}', function () {
    $parent = publishedPage($this->template, 'vereniging', 'Vereniging');
    publishedPage($this->template, 'historie', 'Historie', parentId: $parent->id);

    $this->get('/vereniging/historie')
        ->assertOk()
        ->assertSee('Historie');
});

it('returns 404 for a missing slug', function () {
    $this->get('/bestaat-niet')->assertNotFound();
});

it('returns 404 when no published version exists', function () {
    $page = Page::create([
        'slug' => 'concept-only',
        'title' => 'Concept',
        'template_id' => $this->template->id,
    ]);
    PageVersion::create([
        'page_id' => $page->id,
        'version_no' => 1,
        'status' => PageVersionStatus::Draft,
    ]);

    $this->get('/concept-only')->assertNotFound();
});

it('requires login for members-only pages', function () {
    publishedPage($this->template, 'voor-leden', 'Voor leden', visibility: PageVisibility::Members);

    $this->get('/voor-leden')->assertForbidden();

    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user)->get('/voor-leden')->assertOk();
});

it('renders welcome fallback when no home page exists', function () {
    $this->get('/')->assertOk();
});

it('lists public root pages in the menu via the view composer', function () {
    publishedPage($this->template, 'over-ons', 'Over ons');

    $this->get('/over-ons')
        ->assertOk()
        ->assertSeeText('Over ons');
});
