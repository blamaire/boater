<?php

use App\Services\Cms\TextDiffer;

it('markeert identieke regels als same', function () {
    $rows = (new TextDiffer)->diffLines("a\nb\nc", "a\nb\nc");

    expect($rows)->toHaveCount(3);
    foreach ($rows as $row) {
        expect($row['type'])->toBe('same');
    }
});

it('koppelt een verwijderde en een toegevoegde regel tot één gewijzigde rij', function () {
    $rows = (new TextDiffer)->diffLines("a\nb\nc", "a\nx\nc");

    expect($rows)->toHaveCount(3);
    expect($rows[1])->toBe(['type' => 'changed', 'left' => 'b', 'right' => 'x']);
});

it('markeert een regel die alleen in a voorkomt als removed', function () {
    $rows = (new TextDiffer)->diffLines("a\nb\nc", "a\nc");

    expect($rows)->toHaveCount(3);
    expect($rows[1])->toBe(['type' => 'removed', 'left' => 'b', 'right' => null]);
});

it('markeert een regel die alleen in b voorkomt als added', function () {
    $rows = (new TextDiffer)->diffLines("a\nc", "a\nb\nc");

    expect($rows)->toHaveCount(3);
    expect($rows[1])->toBe(['type' => 'added', 'left' => null, 'right' => 'b']);
});
