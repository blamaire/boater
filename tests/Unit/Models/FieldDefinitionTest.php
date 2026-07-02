<?php

use App\Models\FieldDefinition;
use Database\Seeders\FieldDefinitionSeeder;

beforeEach(function () {
    $this->seed(FieldDefinitionSeeder::class);
});

it('seedt de verwachte kern-persoonsvelden', function () {
    $keys = FieldDefinition::query()->pluck('field_key')->all();

    expect($keys)->toContain(
        'first_name',
        'last_name_prefix',
        'last_name',
        'date_of_birth',
        'email',
        'phone',
        'membership_type',
    );
});

it('markeert naam als niet-verbergbaar, doorzoekbaar en gevoelig', function () {
    $first = FieldDefinition::query()->where('field_key', 'first_name')->firstOrFail();
    $last = FieldDefinition::query()->where('field_key', 'last_name')->firstOrFail();

    foreach ([$first, $last] as $def) {
        expect($def->is_hideable)->toBeFalse()
            ->and($def->is_searchable)->toBeTrue()
            ->and($def->is_sensitive)->toBeTrue()
            ->and($def->default_visible)->toBeTrue();
    }
});

it('markeert contactgegevens als verbergbaar en standaard verborgen', function () {
    foreach (['email', 'phone'] as $key) {
        $def = FieldDefinition::query()->where('field_key', $key)->firstOrFail();
        expect($def->is_hideable)->toBeTrue()
            ->and($def->default_visible)->toBeFalse()
            ->and($def->is_sensitive)->toBeFalse();
    }
});

it('markeert geboortedatum als gevoelig en standaard verborgen', function () {
    $def = FieldDefinition::query()->where('field_key', 'date_of_birth')->firstOrFail();

    expect($def->is_hideable)->toBeTrue()
        ->and($def->is_sensitive)->toBeTrue()
        ->and($def->default_visible)->toBeFalse()
        ->and($def->is_searchable)->toBeFalse();
});

it('markeert membership_type als gevoelig en niet verbergbaar', function () {
    $def = FieldDefinition::query()->where('field_key', 'membership_type')->firstOrFail();

    expect($def->is_hideable)->toBeFalse()
        ->and($def->is_sensitive)->toBeTrue()
        ->and($def->default_visible)->toBeTrue();
});

it('is idempotent — dubbel seedden verandert het aantal niet', function () {
    $countBefore = FieldDefinition::query()->count();
    $this->seed(FieldDefinitionSeeder::class);

    expect(FieldDefinition::query()->count())->toBe($countBefore);
});
