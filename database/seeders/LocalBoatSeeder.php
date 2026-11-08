<?php

namespace Database\Seeders;

use App\Enums\ReservableObjectStatus;
use App\Models\ObjectCategory;
use App\Models\ReservableObject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Testboten voor lokale ontwikkeling aan de module Reserveren (§18): een
 * categorieboom Roeiboten / Zeilboten met per boottype een paar boten,
 * waaronder één die buiten gebruik is. Fictieve namen; alleen bij
 * `APP_ENV=local`, zodat er nooit testboten op test/productie belanden.
 */
class LocalBoatSeeder extends Seeder
{
    /**
     * Hoofdcategorie => [subcategorie => [aantal, plaatsen, locatie]].
     *
     * @var array<string, array<string, array{0: int, 1: int, 2: string}>>
     */
    private const BOATS = [
        'Roeiboten' => [
            'Skiff (1x)' => [3, 1, 'Botenloods'],
            'Dubbeltwee (2x)' => [2, 2, 'Botenloods'],
            'Gestuurde vier (C4+)' => [2, 5, 'Botenloods'],
            'Wherry' => [1, 3, 'Botenloods'],
        ],
        'Zeilboten' => [
            'Optimist' => [4, 1, 'Jeugdsteiger'],
            'RS Feva' => [2, 2, 'Jeugdsteiger'],
            'Laser' => [2, 1, 'Zwaardbotenhelling'],
            'Laser Vago' => [1, 2, 'Zwaardbotenhelling'],
            'Kielboot' => [2, 4, 'Haven'],
        ],
    ];

    public function run(): void
    {
        if (app()->environment() !== 'local') {
            return;
        }

        if (ReservableObject::query()->exists()) {
            return;
        }

        DB::transaction(function (): void {
            $mainOrder = 0;
            foreach (self::BOATS as $mainName => $types) {
                $main = ObjectCategory::query()->create([
                    'name' => $mainName,
                    'slug' => str($mainName)->slug()->toString(),
                    'parent_id' => null,
                    'requires_boat_right' => true,
                    'sort_order' => $mainOrder++,
                ]);

                $typeOrder = 0;
                foreach ($types as $typeName => [$count, $seats, $location]) {
                    $type = ObjectCategory::query()->create([
                        'name' => $typeName,
                        'slug' => str($typeName)->slug()->toString(),
                        'parent_id' => $main->id,
                        'requires_boat_right' => true,
                        'sort_order' => $typeOrder++,
                    ]);

                    // Naam = boottype zonder de afkorting tussen haakjes + volgnummer.
                    $baseName = trim((string) preg_replace('/\s*\(.*\)$/', '', $typeName));
                    for ($i = 1; $i <= $count; $i++) {
                        ReservableObject::query()->create([
                            'object_category_id' => $type->id,
                            'name' => "{$baseName} {$i}",
                            'location' => $location,
                            'attributes' => ['plaatsen' => $seats],
                            'status' => ReservableObjectStatus::Available,
                        ]);
                    }
                }
            }

            // Eén boot buiten gebruik, om dat pad in de reserveringsmodule te kunnen proberen.
            ReservableObject::query()->where('name', 'Skiff 3')->update([
                'status' => ReservableObjectStatus::OutOfService,
            ]);
        });
    }
}
