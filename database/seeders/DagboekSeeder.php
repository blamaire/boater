<?php

namespace Database\Seeders;

use App\Models\Dagboek;
use Illuminate\Database\Seeder;

/**
 * Standaard dagboeken: Verkoop/Inkoop/Memoriaal (singleton) + één Bank en
 * één Kas als startpunt (extra Bank-/Kas-dagboeken via /beheer/dagboeken).
 */
class DagboekSeeder extends Seeder
{
    public function run(): void
    {
        $dagboeken = [
            ['type' => 'verkoop', 'name' => 'Verkoopboek'],
            ['type' => 'inkoop', 'name' => 'Inkoopboek'],
            ['type' => 'memoriaal', 'name' => 'Memoriaal'],
            ['type' => 'bank', 'name' => 'Bank'],
            ['type' => 'kas', 'name' => 'Kas'],
        ];

        foreach ($dagboeken as $dagboek) {
            Dagboek::updateOrCreate(
                ['type' => $dagboek['type'], 'name' => $dagboek['name']],
                [],
            );
        }
    }
}
