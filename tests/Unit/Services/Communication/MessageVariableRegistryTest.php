<?php

use App\Services\Communication\MessageVariableRegistry;

it('geeft de basisset geadresseerde/activiteit-variabelen terug', function () {
    expect(MessageVariableRegistry::baseline())->toBe(['voornaam', 'achternaam', 'titel', 'datum']);
});

it('geeft een lege lijst voor een onbekende sleutel', function () {
    expect(MessageVariableRegistry::for('onbekende_sleutel'))->toBe([]);
});
