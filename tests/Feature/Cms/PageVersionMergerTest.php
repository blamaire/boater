<?php

use App\Enums\PageVersionStatus;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Person;
use App\Models\Template;
use App\Models\User;
use App\Services\Cms\ConflictDetector;
use App\Services\Cms\PageVersionMerger;

beforeEach(function () {
    $this->template = Template::create([
        'name' => 'Standaard',
        'zones' => [['key' => 'hoofd', 'label' => 'Hoofd']],
    ]);
    $this->page = Page::create(['slug' => 'p', 'title' => 'P', 'template_id' => $this->template->id]);
    $this->detector = app(ConflictDetector::class);
    $this->merger = app(PageVersionMerger::class);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->person = Person::create(['first_name' => 'M', 'last_name' => 'Erger', 'account_id' => $user->id]);
});

function mergerVersion(Page $page, array $attributes = []): PageVersion
{
    return PageVersion::create(array_merge([
        'page_id' => $page->id,
        'version_no' => PageVersion::where('page_id', $page->id)->max('version_no') + 1,
        'status' => PageVersionStatus::Draft,
    ], $attributes));
}

it('kiest bij een conflictvrije veld-merge automatisch de gewijzigde kant', function () {
    $base = mergerVersion($this->page, ['og_title' => 'oud', 'meta_description' => 'basis-omschrijving']);
    $mine = mergerVersion($this->page, ['og_title' => 'gewijzigd door mij', 'meta_description' => 'basis-omschrijving']);
    $theirs = mergerVersion($this->page, ['og_title' => 'oud', 'meta_description' => 'gewijzigd door hen']);

    $report = $this->detector->detect($mine, $theirs, $base);
    expect($report->fieldConflicts())->toBeEmpty();

    $resolved = $this->merger->merge($mine, $theirs, $report, $this->person);

    expect($resolved->og_title)->toBe('gewijzigd door mij')
        ->and($resolved->meta_description)->toBe('gewijzigd door hen');
});

it('respecteert de keuze theirs bij een veldconflict', function () {
    $base = mergerVersion($this->page, ['og_title' => 'oud']);
    $mine = mergerVersion($this->page, ['og_title' => 'variant A']);
    $theirs = mergerVersion($this->page, ['og_title' => 'variant B']);

    $report = $this->detector->detect($mine, $theirs, $base);
    expect($report->fieldConflicts())->toHaveCount(1);

    $resolved = $this->merger->merge($mine, $theirs, $report, $this->person, fieldChoices: ['og_title' => 'theirs']);

    expect($resolved->og_title)->toBe('variant B');
});

it('respecteert de keuze manual met een handmatige waarde bij een veldconflict', function () {
    $base = mergerVersion($this->page, ['og_title' => 'oud']);
    $mine = mergerVersion($this->page, ['og_title' => 'variant A']);
    $theirs = mergerVersion($this->page, ['og_title' => 'variant B']);

    $report = $this->detector->detect($mine, $theirs, $base);

    $resolved = $this->merger->merge(
        $mine, $theirs, $report, $this->person,
        fieldChoices: ['og_title' => 'manual'],
        manualFieldValues: ['og_title' => 'handmatig gekozen titel'],
    );

    expect($resolved->og_title)->toBe('handmatig gekozen titel');
});

it('laat een niet-gewijzigd (noop) veld ongewijzigd na de merge', function () {
    $base = mergerVersion($this->page, ['meta_description' => 'stabiele omschrijving']);
    $mine = mergerVersion($this->page, ['meta_description' => 'stabiele omschrijving']);
    $theirs = mergerVersion($this->page, ['meta_description' => 'stabiele omschrijving']);

    $report = $this->detector->detect($mine, $theirs, $base);
    expect($report->fieldEntries->firstWhere('field', 'meta_description')->isNoop())->toBeTrue();

    $resolved = $this->merger->merge($mine, $theirs, $report, $this->person);

    expect($resolved->meta_description)->toBe('stabiele omschrijving');
});
