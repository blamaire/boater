<?php

namespace Database\Seeders;

use App\Models\ApproverGroup;
use Illuminate\Database\Seeder;

/**
 * Seed de centrale goedkeuringsgroepen (§8 / §20 / §22 / §26).
 * Alle policies verwijzen naar deze groepen; beheerders vullen ze
 * met leden via `/beheer/goedkeuringsgroepen`. Beheerder-rol houders
 * zijn hier impliciet lid van (afgedwongen in ReviewerResolver), dus
 * bestaande beheer-goedkeuringen blijven werken zolang de groepen
 * nog niet expliciet gevuld zijn.
 */
class ApproverGroupSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            'Redactie' => 'Beoordeelt content-voorstellen en pagina-publicaties',
            'Ledenadministratie' => 'Beoordeelt lidmaatschapsaanvragen en wijzigingen aan gevoelige persoonsgegevens',
            'Materialen' => 'Beoordeelt reserveringsaanvragen boven drempel of voor een ander',
        ];

        foreach ($groups as $name => $description) {
            ApproverGroup::query()->updateOrCreate(
                ['name' => $name],
                ['description' => $description],
            );
        }
    }
}
