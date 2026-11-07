<?php

use App\Enums\BandBackground;
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
use App\Services\Cms\PageVersionCloner;
use App\Services\Cms\PageVersionDiffSerializer;
use Livewire\Livewire;

beforeEach(function () {
    $this->template = Template::create([
        'name' => 'Standaard',
        'zones' => [['key' => 'hoofd', 'label' => 'Hoofd']],
    ]);
    $this->page = Page::create([
        'slug' => 'achtergrond',
        'title' => 'Achtergrond',
        'type' => 'content',
        'visibility' => 'publiek',
        'template_id' => $this->template->id,
    ]);
});

function versionWithBands(Page $page, PageVersionStatus $status, array $backgrounds): PageVersion
{
    $version = PageVersion::create([
        'page_id' => $page->id,
        'version_no' => PageVersion::where('page_id', $page->id)->count() + 1,
        'status' => $status,
    ]);
    foreach ($backgrounds as $i => $background) {
        $band = Band::create([
            'page_version_id' => $version->id,
            'zone' => 'hoofd',
            'layout' => BandLayout::OneColumn,
            'background' => $background,
            'sort_order' => $i,
        ]);
        Block::create([
            'band_id' => $band->id,
            'column_index' => 0,
            'sort_order' => 0,
            'type' => BlockType::Heading,
            'content' => ['level' => 2, 'text' => 'Band '.$i],
            'visibility' => 'publiek',
        ]);
    }

    return $version;
}

it('geeft een nieuwe band standaard een witte achtergrond', function () {
    $version = versionWithBands($this->page, PageVersionStatus::Draft, []);
    $band = Band::create(['page_version_id' => $version->id, 'zone' => 'hoofd', 'layout' => BandLayout::OneColumn, 'sort_order' => 0]);

    expect($band->fresh()->background)->toBe(BandBackground::White);
});

it('laat de bewerker de achtergrond van een band wijzigen', function () {
    $this->actingAs(User::factory()->create(['email_verified_at' => now()]));
    $version = versionWithBands($this->page, PageVersionStatus::Draft, [BandBackground::White]);
    $band = $version->bands()->first();

    Livewire::test(PageEditor::class, ['versionId' => $version->id])
        ->call('setBandBackground', $band->id, 'zand')
        ->assertHasNoErrors();

    expect($band->fresh()->background)->toBe(BandBackground::Sand);
});

it('neemt de achtergrond mee bij het klonen naar een nieuwe versie', function () {
    $source = versionWithBands($this->page, PageVersionStatus::Published, [BandBackground::Red, BandBackground::Sand]);
    $target = PageVersion::create(['page_id' => $this->page->id, 'version_no' => 2, 'status' => PageVersionStatus::Draft]);

    app(PageVersionCloner::class)->clone($source, $target);

    expect($target->bands()->orderBy('sort_order')->pluck('background')->all())
        ->toBe([BandBackground::Red, BandBackground::Sand]);
});

it('neemt de achtergrond op in de rauwe JSON-weergave van een versie', function () {
    $version = versionWithBands($this->page, PageVersionStatus::Draft, [BandBackground::Dark]);

    $raw = app(PageVersionDiffSerializer::class)->raw($version);

    expect($raw['bands'][0]['background'])->toBe('donker');
});

it('ziet een gewijzigde achtergrond als niet-gepubliceerde wijziging', function () {
    $published = versionWithBands($this->page, PageVersionStatus::Published, [BandBackground::White]);
    $this->page->update(['published_version_id' => $published->id]);
    $draft = PageVersion::create(['page_id' => $this->page->id, 'version_no' => 2, 'status' => PageVersionStatus::Draft]);
    app(PageVersionCloner::class)->clone($published, $draft);

    expect($draft->fresh()->hasUnpublishedChanges())->toBeFalse();

    $draft->bands()->first()->update(['background' => BandBackground::Sand]);

    expect($draft->fresh()->hasUnpublishedChanges())->toBeTrue();
});

it('rendert de achtergrond over de volle breedte en voegt opeenvolgende banden met dezelfde achtergrond samen tot één sectie', function () {
    $version = versionWithBands($this->page, PageVersionStatus::Published, [BandBackground::Sand, BandBackground::Sand, BandBackground::Red]);
    $this->page->update(['published_version_id' => $version->id]);

    $html = $this->get($this->page->publicUrl())->assertOk()->getContent();

    // Eerste zandband: ruimte boven, kleine tussenruimte onder (groep loopt door).
    expect($html)->toContain('class="bg-sand-100 pt-12 md:pt-20 pb-8 md:pb-10"')
        // Tweede zandband: geen ruimte boven, ruime afsluiting onder.
        ->toContain('class="bg-sand-100 pb-12 md:pb-20"')
        // Rode band: witte tekst via band-inverted.
        ->toContain('bg-rzvg-500 text-white band-inverted');
});
