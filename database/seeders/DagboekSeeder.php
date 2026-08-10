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
            ['number' => 1, 'type' => 'verkoop', 'name' => 'Verkoopboek'],
            ['number' => 2, 'type' => 'inkoop', 'name' => 'Inkoopboek'],
            ['number' => 3, 'type' => 'bank', 'name' => 'Bank'],
            ['number' => 4, 'type' => 'kas', 'name' => 'Kas'],
            ['number' => 5, 'type' => 'memoriaal', 'name' => 'Memoriaal'],
        ];

        foreach ($dagboeken as $dagboek) {
            Dagboek::updateOrCreate(
                ['type' => $dagboek['type'], 'name' => $dagboek['name']],
                ['number' => $dagboek['number']],
            );
        }
    }
}
