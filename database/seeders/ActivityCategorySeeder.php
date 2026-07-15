<?php

namespace Database\Seeders;

use App\Models\ActivityCategory;
use Illuminate\Database\Seeder;

class ActivityCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Roeien', 'slug' => 'roeien', 'sort_order' => 10],
            ['name' => 'Zeilen', 'slug' => 'zeilen', 'sort_order' => 20],
            ['name' => 'Wedstrijd', 'slug' => 'wedstrijd', 'sort_order' => 30],
            ['name' => 'Sociëteit', 'slug' => 'societeit', 'sort_order' => 40],
            ['name' => 'Vergadering', 'slug' => 'vergadering', 'sort_order' => 50],
            ['name' => 'Overig', 'slug' => 'overig', 'sort_order' => 99],
        ];

        foreach ($categories as $category) {
            ActivityCategory::updateOrCreate(['slug' => $category['slug']], $category);
        }
    }
}
