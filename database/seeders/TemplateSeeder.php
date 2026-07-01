<?php

namespace Database\Seeders;

use App\Models\Template;
use Illuminate\Database\Seeder;

class TemplateSeeder extends Seeder
{
    public function run(): void
    {
        Template::updateOrCreate(
            ['name' => 'Standaard'],
            [
                'zones' => [
                    ['key' => 'hoofd', 'label' => 'Hoofd'],
                ],
            ],
        );
    }
}
