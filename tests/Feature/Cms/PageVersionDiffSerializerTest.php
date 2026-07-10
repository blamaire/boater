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
use App\Services\Cms\PageVersionDiffSerializer;

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
    $this->serializer = app(PageVersionDiffSerializer::class);
    $this->detector = app(ConflictDetector::class);
});

function newVersion(Page $page, PageVersionStatus $status): PageVersion
{
    return PageVersion::create([
        'page_id' => $page->id,
        'version_no' => PageVersion::where('page_id', $page->id)->max('version_no') + 1,
        'status' => $status,
    ]);
}

function addBand(PageVersion $v, ?int $originBandId = null): Band
{
    return Band::create([
        'page_version_id' => $v->id,
        'origin_band_id' => $originBandId,
        'zone' => 'hoofd',
        'layout' => BandLayout::OneColumn,
        'sort_order' => 0,
    ]);
}

function addBlock(Band $band, array $content, ?int $originBlockId = null): Block
{
    return Block::create([
        'band_id' => $band->id,
        'origin_block_id' => $originBlockId,
        'column_index' => 0,
        'sort_order' => 0,
        'type' => BlockType::Text,
        'content' => $content,
    ]);
}

it('serializeert een structured diff met a en b content per blok', function () {
    $a = newVersion($this->page, PageVersionStatus::Draft);
    $bandA = addBand($a);
    $blockA = addBlock($bandA, ['html' => '<p>a</p>']);

    $b = newVersion($this->page, PageVersionStatus::Draft);
    $bandB = addBand($b, originBandId: $bandA->id);
    addBlock($bandB, ['html' => '<p>b</p>'], originBlockId: $blockA->id);

    $report = $this->detector->detect($a, $b, null);
    $json = $this->serializer->structured($report);

    expect($json)->toHaveCount(1);
    expect($json[0]['origin_block_id'])->toBe($blockA->id);
    expect($json[0]['a']['content'])->toBe(['html' => '<p>a</p>']);
    expect($json[0]['b']['content'])->toBe(['html' => '<p>b</p>']);
});

it('markeert een blok dat alleen in a bestaat', function () {
    $a = newVersion($this->page, PageVersionStatus::Draft);
    $bandA = addBand($a);
    addBlock($bandA, ['html' => '<p>alleen-a</p>']);

    $b = newVersion($this->page, PageVersionStatus::Draft);

    $report = $this->detector->detect($a, $b, null);
    $json = $this->serializer->structured($report);

    expect($json[0]['b'])->toBeNull();
    expect($json[0]['a'])->not->toBeNull();
});

it('geeft de rauwe versie-inhoud terug met banden, blokken en volgorde', function () {
    $v = newVersion($this->page, PageVersionStatus::Draft);
    $band = addBand($v);
    addBlock($band, ['html' => '<p>een</p>']);
    addBlock($band, ['html' => '<p>twee</p>']);

    $raw = $this->serializer->raw($v);

    expect($raw['version_no'])->toBe($v->version_no);
    expect($raw['bands'])->toHaveCount(1);
    expect($raw['bands'][0]['blocks'])->toHaveCount(2);
    expect($raw['bands'][0]['blocks'][0]['content'])->toBe(['html' => '<p>een</p>']);
});
