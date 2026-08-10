<?php

use App\Enums\PageVersionStatus;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Template;
use App\Services\Cms\ConflictDetector;
use App\Services\Cms\MetaFieldConflictDetector;

beforeEach(function () {
    $this->template = Template::create([
        'name' => 'Standaard',
        'zones' => [['key' => 'hoofd', 'label' => 'Hoofd']],
    ]);
    $this->page = Page::create(['slug' => 'p', 'title' => 'P', 'template_id' => $this->template->id]);
    $this->detector = app(MetaFieldConflictDetector::class);
});

function metaVersion(Page $page, array $attributes = []): PageVersion
{
    return PageVersion::create(array_merge([
        'page_id' => $page->id,
        'version_no' => PageVersion::where('page_id', $page->id)->max('version_no') + 1,
        'status' => PageVersionStatus::Draft,
    ], $attributes));
}

it('classificeert een veld als unchanged wanneer geen van beide kanten wijzigt', function () {
    $base = metaVersion($this->page, ['og_title' => 'oud']);
    $mine = metaVersion($this->page, ['og_title' => 'oud']);
    $theirs = metaVersion($this->page, ['og_title' => 'oud']);

    $result = $this->detector->detect($mine, $theirs, $base);
    $diff = $result->firstWhere('field', 'og_title');

    expect($diff->type)->toBe('unchanged')->and($diff->isNoop())->toBeTrue();
});

it('classificeert een veld als edited_by_me wanneer alleen mine wijzigt', function () {
    $base = metaVersion($this->page, ['og_title' => 'oud']);
    $mine = metaVersion($this->page, ['og_title' => 'nieuw']);
    $theirs = metaVersion($this->page, ['og_title' => 'oud']);

    $diff = $this->detector->detect($mine, $theirs, $base)->firstWhere('field', 'og_title');

    expect($diff->type)->toBe('edited_by_me');
});

it('classificeert een veld als edited_by_theirs wanneer alleen theirs wijzigt', function () {
    $base = metaVersion($this->page, ['og_title' => 'oud']);
    $mine = metaVersion($this->page, ['og_title' => 'oud']);
    $theirs = metaVersion($this->page, ['og_title' => 'nieuw']);

    $diff = $this->detector->detect($mine, $theirs, $base)->firstWhere('field', 'og_title');

    expect($diff->type)->toBe('edited_by_theirs');
});

it('classificeert een veld als auto_mergeable wanneer beide naar dezelfde waarde wijzigen', function () {
    $base = metaVersion($this->page, ['og_title' => 'oud']);
    $mine = metaVersion($this->page, ['og_title' => 'nieuw']);
    $theirs = metaVersion($this->page, ['og_title' => 'nieuw']);

    $diff = $this->detector->detect($mine, $theirs, $base)->firstWhere('field', 'og_title');

    expect($diff->type)->toBe('auto_mergeable');
});

it('classificeert een veld als conflict_edit_edit wanneer beide naar een verschillende waarde wijzigen', function () {
    $base = metaVersion($this->page, ['og_title' => 'oud']);
    $mine = metaVersion($this->page, ['og_title' => 'variant A']);
    $theirs = metaVersion($this->page, ['og_title' => 'variant B']);

    $diff = $this->detector->detect($mine, $theirs, $base)->firstWhere('field', 'og_title');

    expect($diff->type)->toBe('conflict_edit_edit')->and($diff->isConflict())->toBeTrue();
});

it('classificeert een base-loze two-way diff als unchanged bij gelijke waarden', function () {
    $mine = metaVersion($this->page, ['og_title' => 'zelfde']);
    $theirs = metaVersion($this->page, ['og_title' => 'zelfde']);

    $diff = $this->detector->detect($mine, $theirs, null)->firstWhere('field', 'og_title');

    expect($diff->type)->toBe('unchanged');
});

it('classificeert een base-loze two-way diff als conflict_edit_edit bij afwijkende waarden', function () {
    $mine = metaVersion($this->page, ['og_title' => 'variant A']);
    $theirs = metaVersion($this->page, ['og_title' => 'variant B']);

    $diff = $this->detector->detect($mine, $theirs, null)->firstWhere('field', 'og_title');

    expect($diff->type)->toBe('conflict_edit_edit');
});

it('heeft hasConflicts() op true staan bij uitsluitend een veldconflict zonder blokconflict', function () {
    $base = metaVersion($this->page, ['og_title' => 'oud']);
    $mine = metaVersion($this->page, ['og_title' => 'variant A']);
    $theirs = metaVersion($this->page, ['og_title' => 'variant B']);

    $report = app(ConflictDetector::class)->detect($mine, $theirs, $base);

    expect($report->conflicts())->toBeEmpty()
        ->and($report->fieldConflicts())->toHaveCount(1)
        ->and($report->hasConflicts())->toBeTrue();
});
