<?php

namespace Database\Seeders;

use App\Models\FieldDefinition;
use Illuminate\Database\Seeder;

/**
 * §21.2 — Seed de bekende Person-/Membership-velden met hun gedrag
 * (verbergbaar, doorzoekbaar, gevoelig, standaardzichtbaar).
 */
class FieldDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->definitions() as $definition) {
            FieldDefinition::updateOrCreate(
                ['field_key' => $definition['field_key']],
                $definition,
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function definitions(): array
    {
        return [
            // Naam is de identiteit: gevoelig (wijziging via goedkeuring), doorzoekbaar,
            // altijd zichtbaar en NIET verbergbaar (anders wordt zoeken zinloos).
            [
                'field_key' => 'first_name',
                'label' => 'Voornaam',
                'is_hideable' => false,
                'is_searchable' => true,
                'is_sensitive' => true,
                'default_visible' => true,
            ],
            [
                'field_key' => 'last_name_prefix',
                'label' => 'Tussenvoegsel',
                'is_hideable' => false,
                'is_searchable' => true,
                'is_sensitive' => true,
                'default_visible' => true,
            ],
            [
                'field_key' => 'last_name',
                'label' => 'Achternaam',
                'is_hideable' => false,
                'is_searchable' => true,
                'is_sensitive' => true,
                'default_visible' => true,
            ],
            // Geboortedatum: gevoelig; wél verbergbaar en standaard verborgen (privacy).
            [
                'field_key' => 'date_of_birth',
                'label' => 'Geboortedatum',
                'is_hideable' => true,
                'is_searchable' => false,
                'is_sensitive' => true,
                'default_visible' => false,
            ],
            // Contactgegevens: niet gevoelig (mag zelf wijzigen), verbergbaar, standaard verborgen.
            [
                'field_key' => 'email',
                'label' => 'E-mailadres',
                'is_hideable' => true,
                'is_searchable' => false,
                'is_sensitive' => false,
                'default_visible' => false,
            ],
            [
                'field_key' => 'phone',
                'label' => 'Telefoonnummer',
                'is_hideable' => true,
                'is_searchable' => false,
                'is_sensitive' => false,
                'default_visible' => false,
            ],
            // Lidmaatschapsvorm: gevoelig (wijziging via goedkeuring),
            // altijd zichtbaar en NIET verbergbaar (informatie die de gids duidt).
            [
                'field_key' => 'membership_type',
                'label' => 'Lidmaatschapsvorm',
                'is_hideable' => false,
                'is_searchable' => false,
                'is_sensitive' => true,
                'default_visible' => true,
            ],
        ];
    }
}
