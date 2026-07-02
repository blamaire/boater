<?php

namespace App\Services\Portal;

use App\Models\FieldDefinition;
use App\Models\Person;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * §21.3 — Bepaalt per persoon welke velden zichtbaar zijn in Leden zoeken.
 *
 * Regels:
 * - Naamvelden (first_name, last_name_prefix, last_name) zijn altijd zichtbaar.
 * - Voor overige velden: als de tabel `person_field_visibilities` bestaat en
 *   een record heeft voor dit veld → gebruik die vlag; anders val terug op
 *   FieldDefinition::default_visible.
 * - Voor minderjarigen (§21.3): contactgegevens (email, phone) zijn standaard
 *   verborgen — alleen zichtbaar als er een expliciete opt-in (visible_to_members = true)
 *   staat. Dus niet via default_visible.
 *
 * De klasse werkt volledig zonder de PersonFieldVisibility-tabel; die wordt
 * door een parallelle agent gebouwd. Wanneer die tabel bestaat, wordt de
 * user-choice (explicit override) automatisch gerespecteerd.
 */
class PersonFieldVisibilityResolver
{
    private const ALWAYS_VISIBLE = ['first_name', 'last_name_prefix', 'last_name'];

    private const MINOR_HIDDEN_BY_DEFAULT = ['email', 'phone'];

    /** @var array<string, FieldDefinition>|null */
    private ?array $definitionCache = null;

    /**
     * Geef de field_keys terug die zichtbaar zijn voor het huidige lid.
     *
     * @return array<int, string>
     */
    public function visibleFieldsFor(Person $person): array
    {
        $definitions = $this->definitions();
        $explicit = $this->explicitVisibility($person);
        $isMinor = $this->isMinor($person);

        $visible = [];
        foreach ($definitions as $key => $definition) {
            if ($this->isFieldVisible($key, $definition, $explicit, $isMinor)) {
                $visible[] = $key;
            }
        }

        // Zorg dat naam altijd voorop staat en aanwezig is (voor het geval de
        // FIELD_DEFINITION-seeder nog niet gedraaid heeft).
        foreach (array_reverse(self::ALWAYS_VISIBLE) as $nameKey) {
            if (! in_array($nameKey, $visible, true)) {
                array_unshift($visible, $nameKey);
            }
        }

        return $visible;
    }

    /**
     * @param  array<string, bool>  $explicit
     */
    private function isFieldVisible(
        string $key,
        FieldDefinition $definition,
        array $explicit,
        bool $isMinor,
    ): bool {
        if (in_array($key, self::ALWAYS_VISIBLE, true)) {
            return true;
        }

        // Minderjarigen: contactgegevens alleen zichtbaar met expliciete opt-in.
        if ($isMinor && in_array($key, self::MINOR_HIDDEN_BY_DEFAULT, true)) {
            return $explicit[$key] ?? false;
        }

        // Als er een expliciete keuze bestaat, respecteer die (user-choice > default).
        if (array_key_exists($key, $explicit)) {
            return $explicit[$key];
        }

        return (bool) $definition->default_visible;
    }

    /**
     * Haal expliciete zichtbaarheidsinstellingen op voor deze persoon.
     * Retourneert een lege array als de PersonFieldVisibility-tabel nog niet bestaat.
     *
     * @return array<string, bool>
     */
    private function explicitVisibility(Person $person): array
    {
        if (! Schema::hasTable('person_field_visibilities')) {
            return [];
        }

        $rows = [];
        $records = DB::table('person_field_visibilities')
            ->where('person_id', $person->id)
            ->get(['field_key', 'visible_to_members']);

        foreach ($records as $record) {
            $rows[(string) $record->field_key] = (bool) $record->visible_to_members;
        }

        return $rows;
    }

    private function isMinor(Person $person): bool
    {
        $dob = $person->date_of_birth;
        if ($dob === null) {
            return false;
        }

        return Carbon::instance($dob)->age < 18;
    }

    /**
     * @return array<string, FieldDefinition>
     */
    private function definitions(): array
    {
        if ($this->definitionCache !== null) {
            return $this->definitionCache;
        }

        $map = [];
        foreach (FieldDefinition::query()->get() as $def) {
            $map[$def->field_key] = $def;
        }

        return $this->definitionCache = $map;
    }
}
