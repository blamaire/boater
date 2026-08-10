<?php

use App\Services\Cms\FieldDiff;

it('geeft een Nederlandse omschrijving voor unchanged', function () {
    $diff = new FieldDiff('og_title', 'unchanged', null, 'a', 'a');

    expect($diff->label())->toBe('Ongewijzigd');
});

it('geeft een Nederlandse omschrijving voor edited_by_theirs', function () {
    $diff = new FieldDiff('og_title', 'edited_by_theirs', 'oud', 'oud', 'nieuw');

    expect($diff->label())->toBe('Gewijzigd in de rechterversie');
});

it('laat de mine/theirs-omschrijving contextafhankelijk overschrijven', function () {
    $diff = new FieldDiff('og_title', 'edited_by_me', 'oud', 'nieuw', 'oud');

    expect($diff->label('jouw versie', 'de gepubliceerde versie'))->toBe('Gewijzigd in jouw versie');
});

it('geeft een Nederlandse omschrijving voor auto_mergeable', function () {
    $diff = new FieldDiff('og_title', 'auto_mergeable', 'oud', 'nieuw', 'nieuw');

    expect($diff->label())->toBe('Beide gewijzigd, automatisch samengevoegd');
});

it('geeft een Nederlandse omschrijving voor conflict_edit_edit', function () {
    $diff = new FieldDiff('og_title', 'conflict_edit_edit', 'oud', 'A', 'B');

    expect($diff->label())->toBe('Conflict — beide gewijzigd op hetzelfde veld');
});

it('vertaalt het veldnaam naar een leesbaar label', function () {
    expect((new FieldDiff('meta_description', 'unchanged', null, null, null))->fieldLabel())->toBe('Meta-omschrijving')
        ->and((new FieldDiff('og_title', 'unchanged', null, null, null))->fieldLabel())->toBe('OG-titel')
        ->and((new FieldDiff('og_description', 'unchanged', null, null, null))->fieldLabel())->toBe('OG-omschrijving')
        ->and((new FieldDiff('og_image_media_asset_id', 'unchanged', null, null, null))->fieldLabel())->toBe('OG-afbeelding');
});
