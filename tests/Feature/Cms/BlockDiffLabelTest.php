<?php

use App\Services\Cms\BlockDiff;

it('geeft een Nederlandse omschrijving voor added_by_theirs', function () {
    $diff = new BlockDiff(1, 'added_by_theirs', null, null, null, []);

    expect($diff->label())->toBe('Toegevoegd in de rechterversie');
});

it('laat de mine/theirs-omschrijving contextafhankelijk overschrijven', function () {
    $diff = new BlockDiff(1, 'added_by_me', null, null, null, []);

    expect($diff->label('jouw versie', 'de gepubliceerde versie'))->toBe('Toegevoegd in jouw versie');
});

it('geeft een Nederlandse omschrijving voor conflict_edit_edit', function () {
    $diff = new BlockDiff(1, 'conflict_edit_edit', null, null, null, []);

    expect($diff->label())->toBe('Conflict — beide gewijzigd op hetzelfde veld');
});

it('geeft een Nederlandse omschrijving voor unchanged', function () {
    $diff = new BlockDiff(1, 'unchanged', null, null, null, []);

    expect($diff->label())->toBe('Ongewijzigd');
});
