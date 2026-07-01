<?php

use App\Enums\BandLayout;
use App\Enums\BlockType;
use App\Enums\PageVersionStatus;
use App\Livewire\Admin\PageEditor;
use App\Models\Band;
use App\Models\Block;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Template;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->template = Template::create([
        'name' => 'Standaard',
        'zones' => [['key' => 'hoofd', 'label' => 'Hoofd']],
    ]);
    $this->page = Page::create([
        'slug' => 'test',
        'title' => 'Test',
        'template_id' => $this->template->id,
    ]);
    $this->version = PageVersion::create([
        'page_id' => $this->page->id,
        'version_no' => 1,
        'status' => PageVersionStatus::Draft,
    ]);
    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user);
});

it('adds a band with the chosen layout via addBand', function () {
    Livewire::test(PageEditor::class, ['versionId' => $this->version->id])
        ->call('addBand', 0, 2)
        ->assertHasNoErrors();

    $band = Band::where('page_version_id', $this->version->id)->firstOrFail();
    expect($band->layout)->toBe(BandLayout::TwoColumns)
        ->and($band->sort_order)->toBe(0);
});

it('inserts a new band at the requested position and shifts the rest', function () {
    Band::create(['page_version_id' => $this->version->id, 'zone' => 'hoofd', 'layout' => BandLayout::OneColumn, 'sort_order' => 0]);
    Band::create(['page_version_id' => $this->version->id, 'zone' => 'hoofd', 'layout' => BandLayout::OneColumn, 'sort_order' => 1]);

    Livewire::test(PageEditor::class, ['versionId' => $this->version->id])
        ->call('addBand', 1, 3);

    $bands = Band::where('page_version_id', $this->version->id)->orderBy('sort_order')->get();
    expect($bands)->toHaveCount(3)
        ->and($bands[1]->layout)->toBe(BandLayout::ThreeColumns);
});

it('adds a block to a band via addBlock', function () {
    $band = Band::create([
        'page_version_id' => $this->version->id,
        'zone' => 'hoofd',
        'layout' => BandLayout::OneColumn,
        'sort_order' => 0,
    ]);

    Livewire::test(PageEditor::class, ['versionId' => $this->version->id])
        ->call('addBlock', $band->id, 0, BlockType::Text->value);

    $block = Block::where('band_id', $band->id)->firstOrFail();
    expect($block->type)->toBe(BlockType::Text);
});

it('refuses to mutate a version that is not in draft status', function () {
    $this->version->update(['status' => PageVersionStatus::Published]);

    Livewire::test(PageEditor::class, ['versionId' => $this->version->id])
        ->call('addBand', 0, 1)
        ->assertStatus(403);
});
