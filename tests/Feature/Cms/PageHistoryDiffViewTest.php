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

it('klapt ongewijzigde blokken dicht en toont Nederlandse omschrijvingen i.p.v. rauwe type-strings', function () {
    $template = Template::create(['name' => 'Standaard', 'zones' => [['key' => 'hoofd', 'label' => 'Hoofd']]]);
    $page = Page::create([
        'slug' => 'diff-view-test',
        'title' => 'Diff view test',
        'type' => 'content',
        'visibility' => 'publiek',
        'template_id' => $template->id,
    ]);

    $a = PageVersion::create(['page_id' => $page->id, 'version_no' => 1, 'status' => PageVersionStatus::Draft]);
    $bandA = Band::create(['page_version_id' => $a->id, 'zone' => 'hoofd', 'layout' => BandLayout::OneColumn, 'sort_order' => 0]);
    $unchangedBlockA = Block::create([
        'band_id' => $bandA->id, 'column_index' => 0, 'sort_order' => 0,
        'type' => BlockType::Text, 'content' => ['html' => 'zelfde inhoud'],
    ]);
    $changedBlockA = Block::create([
        'band_id' => $bandA->id, 'column_index' => 0, 'sort_order' => 1,
        'type' => BlockType::Text, 'content' => ['html' => 'variant A'],
    ]);

    $b = PageVersion::create(['page_id' => $page->id, 'version_no' => 2, 'status' => PageVersionStatus::Draft]);
    $bandB = Band::create(['page_version_id' => $b->id, 'origin_band_id' => $bandA->id, 'zone' => 'hoofd', 'layout' => BandLayout::OneColumn, 'sort_order' => 0]);
    Block::create([
        'band_id' => $bandB->id, 'origin_block_id' => $unchangedBlockA->id, 'column_index' => 0, 'sort_order' => 0,
        'type' => BlockType::Text, 'content' => ['html' => 'zelfde inhoud'],
    ]);
    Block::create([
        'band_id' => $bandB->id, 'origin_block_id' => $changedBlockA->id, 'column_index' => 0, 'sort_order' => 1,
        'type' => BlockType::Text, 'content' => ['html' => 'variant B'],
    ]);

    $report = app(ConflictDetector::class)->detect($a, $b, null);
    $html = view('cms.blocks.diff', ['report' => $report, 'a' => $a, 'b' => $b])->render();

    expect($html)->toContain('Alles uitklappen')
        ->toContain('Ongewijzigd')
        ->toContain('Conflict — beide gewijzigd op hetzelfde veld')
        ->not->toContain('conflict_edit_edit')
        ->not->toContain('added_by_theirs')
        // Elk blok toont een pijltje dat aangeeft of het in- of uitgeklapt is.
        ->toContain('group-open:rotate-90')
        // De inhoud van het ongewijzigde blok wordt wél getoond (in het
        // dichtgeklapte <details>-blok, klaar om te tonen bij het openklappen).
        ->toContain('zelfde inhoud');

    // Het ongewijzigde blok staat standaard dichtgeklapt...
    preg_match('/<details[^>]*wire:key="diff-'.$unchangedBlockA->id.'"[^>]*>/', $html, $unchangedTag);
    expect($unchangedTag[0])->not->toContain(' open');

    // ...maar het gewijzigde blok, met de eigenlijke verschillen, staat standaard open.
    preg_match('/<details[^>]*wire:key="diff-'.$changedBlockA->id.'"[^>]*>/', $html, $changedTag);
    expect($changedTag[0])->toContain(' open');
});

it('toont "de linkerversie"/"de rechterversie" i.p.v. "versie A"/"versie B"', function () {
    $template = Template::create(['name' => 'Standaard', 'zones' => [['key' => 'hoofd', 'label' => 'Hoofd']]]);
    $page = Page::create([
        'slug' => 'diff-view-links-rechts',
        'title' => 'Diff view links/rechts',
        'type' => 'content',
        'visibility' => 'publiek',
        'template_id' => $template->id,
    ]);

    $a = PageVersion::create(['page_id' => $page->id, 'version_no' => 1, 'status' => PageVersionStatus::Draft]);
    $bandA = Band::create(['page_version_id' => $a->id, 'zone' => 'hoofd', 'layout' => BandLayout::OneColumn, 'sort_order' => 0]);
    Block::create([
        'band_id' => $bandA->id, 'column_index' => 0, 'sort_order' => 0,
        'type' => BlockType::Text, 'content' => ['html' => 'alleen in a'],
    ]);

    $b = PageVersion::create(['page_id' => $page->id, 'version_no' => 2, 'status' => PageVersionStatus::Draft]);

    $report = app(ConflictDetector::class)->detect($a, $b, null);
    $html = view('cms.blocks.diff', ['report' => $report, 'a' => $a, 'b' => $b])->render();

    expect($html)->toContain('Toegevoegd in de linkerversie')
        ->not->toContain('versie A')
        ->not->toContain('versie B');
});
