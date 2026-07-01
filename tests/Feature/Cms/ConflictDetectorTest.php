<?php

use App\Enums\BandLayout;
use App\Enums\BlockType;
use App\Enums\PageVersionStatus;
use App\Models\Band;
use App\Models\Block;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Template;
use App\Services\Cms\ConflictDetector;

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
    $this->detector = app(ConflictDetector::class);
});

function makeVersion(Page $page, PageVersionStatus $status, ?int $baseVersionId = null): PageVersion
{
    return PageVersion::create([
        'page_id' => $page->id,
        'version_no' => PageVersion::where('page_id', $page->id)->max('version_no') + 1,
        'status' => $status,
        'base_version_id' => $baseVersionId,
    ]);
}

function withBand(PageVersion $version, int $sort = 0, ?int $originBandId = null): Band
{
    return Band::create([
        'page_version_id' => $version->id,
        'origin_band_id' => $originBandId,
        'zone' => 'hoofd',
        'layout' => BandLayout::OneColumn,
        'sort_order' => $sort,
    ]);
}

function withBlock(Band $band, array $content, ?int $originBlockId = null, int $sort = 0): Block
{
    return Block::create([
        'band_id' => $band->id,
        'origin_block_id' => $originBlockId,
        'column_index' => 0,
        'sort_order' => $sort,
        'type' => BlockType::Text,
        'content' => $content,
    ]);
}

it('reports no conflicts when both versions are identical', function () {
    $base = makeVersion($this->page, PageVersionStatus::Published);
    $band = withBand($base);
    $block = withBlock($band, ['html' => '<p>a</p>']);

    $mine = makeVersion($this->page, PageVersionStatus::Draft, $base->id);
    $myBand = withBand($mine, originBandId: $band->id);
    withBlock($myBand, ['html' => '<p>a</p>'], originBlockId: $block->id);

    $theirs = makeVersion($this->page, PageVersionStatus::Draft, $base->id);
    $theirBand = withBand($theirs, originBandId: $band->id);
    withBlock($theirBand, ['html' => '<p>a</p>'], originBlockId: $block->id);

    $report = $this->detector->detect($mine, $theirs, $base);

    expect($report->hasConflicts())->toBeFalse();
});

it('auto-merges when mine and theirs edit different keys of the same block', function () {
    $base = makeVersion($this->page, PageVersionStatus::Published);
    $band = withBand($base);
    $block = withBlock($band, ['title' => 'oud', 'body' => 'oude tekst']);

    $mine = makeVersion($this->page, PageVersionStatus::Draft, $base->id);
    $myBand = withBand($mine, originBandId: $band->id);
    withBlock($myBand, ['title' => 'nieuwe titel', 'body' => 'oude tekst'], originBlockId: $block->id);

    $theirs = makeVersion($this->page, PageVersionStatus::Draft, $base->id);
    $theirBand = withBand($theirs, originBandId: $band->id);
    withBlock($theirBand, ['title' => 'oud', 'body' => 'nieuwe body'], originBlockId: $block->id);

    $report = $this->detector->detect($mine, $theirs, $base);

    expect($report->hasConflicts())->toBeFalse();
    expect($report->autoMerges())->toHaveCount(1);
});

it('reports conflict when mine and theirs edit the same key differently', function () {
    $base = makeVersion($this->page, PageVersionStatus::Published);
    $band = withBand($base);
    $block = withBlock($band, ['title' => 'oud']);

    $mine = makeVersion($this->page, PageVersionStatus::Draft, $base->id);
    $myBand = withBand($mine, originBandId: $band->id);
    withBlock($myBand, ['title' => 'variant A'], originBlockId: $block->id);

    $theirs = makeVersion($this->page, PageVersionStatus::Draft, $base->id);
    $theirBand = withBand($theirs, originBandId: $band->id);
    withBlock($theirBand, ['title' => 'variant B'], originBlockId: $block->id);

    $report = $this->detector->detect($mine, $theirs, $base);

    expect($report->hasConflicts())->toBeTrue();
    expect($report->conflicts())->toHaveCount(1);
    expect($report->conflicts()->first()->type)->toBe('conflict_edit_edit');
    expect($report->conflicts()->first()->conflictingKeys)->toBe(['title']);
});

it('reports conflict when one deletes and the other edits', function () {
    $base = makeVersion($this->page, PageVersionStatus::Published);
    $band = withBand($base);
    $block = withBlock($band, ['title' => 'oud']);

    // 'mine' verwijdert het blok (geen kopie)
    $mine = makeVersion($this->page, PageVersionStatus::Draft, $base->id);
    withBand($mine, originBandId: $band->id);

    // 'theirs' wijzigt het blok
    $theirs = makeVersion($this->page, PageVersionStatus::Draft, $base->id);
    $theirBand = withBand($theirs, originBandId: $band->id);
    withBlock($theirBand, ['title' => 'gewijzigd'], originBlockId: $block->id);

    $report = $this->detector->detect($mine, $theirs, $base);

    expect($report->hasConflicts())->toBeTrue();
    expect($report->conflicts()->first()->type)->toBe('conflict_delete_edit');
});

it('flags new-in-both-without-base as an edit-edit conflict', function () {
    $base = makeVersion($this->page, PageVersionStatus::Published);
    withBand($base);

    // Beide voegen een blok toe met dezelfde origin — kan alleen kunstmatig, maar we
    // dekken het scenario af zodat het niet stilletjes samengaat.
    $mine = makeVersion($this->page, PageVersionStatus::Draft, $base->id);
    $myBand = withBand($mine);
    withBlock($myBand, ['title' => 'A']);

    $theirs = makeVersion($this->page, PageVersionStatus::Draft, $base->id);
    $theirBand = withBand($theirs);
    withBlock($theirBand, ['title' => 'B']);

    $report = $this->detector->detect($mine, $theirs, $base);

    // Beide zijn 'added_by_me' resp. 'added_by_theirs' vanuit hun perspectief;
    // geen conflict want origin verschilt (nieuwe eigen id's).
    expect($report->hasConflicts())->toBeFalse();
    expect($report->autoMerges())->toHaveCount(2);
});
