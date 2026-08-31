<?php

namespace Database\Seeders;

use App\Models\MessageTemplateFolder;
use Illuminate\Database\Seeder;

/**
 * De twee vaste root-mappen voor berichtsjablonen (§24.4) — moet vóór
 * MessageTemplateSeeder draaien, die de bestaande sjablonen in
 * "Systeemberichten" plaatst.
 */
class MessageTemplateFolderSeeder extends Seeder
{
    public function run(): void
    {
        MessageTemplateFolder::query()->updateOrCreate(
            ['name' => 'Systeemberichten', 'parent_id' => null],
            ['is_system' => true],
        );

        MessageTemplateFolder::query()->updateOrCreate(
            ['name' => 'Mailings', 'parent_id' => null],
            ['is_system' => true],
        );
    }
}
