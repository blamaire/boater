<?php

use App\Enums\BlockType;
use App\Enums\PageType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // De home-systeempagina (migratie 2026_07_02_140001) hardcodede de "Lid
        // worden"-knop op /pagina/lid-worden. Nu er een dedicated /lid-worden
        // route bestaat, wijs de knop daarheen.
        $homePageId = DB::table('pages')
            ->whereNull('parent_id')
            ->where('slug', 'home')
            ->where('type', PageType::System->value)
            ->value('id');

        if ($homePageId === null) {
            return;
        }

        $bandIds = DB::table('bands')
            ->join('page_versions', 'bands.page_version_id', '=', 'page_versions.id')
            ->where('page_versions.page_id', $homePageId)
            ->pluck('bands.id');

        $blocks = DB::table('blocks')
            ->whereIn('band_id', $bandIds)
            ->where('type', BlockType::Button->value)
            ->get(['id', 'content']);

        foreach ($blocks as $block) {
            $content = json_decode((string) $block->content, true);
            if (! is_array($content) || ($content['href'] ?? null) !== '/pagina/lid-worden') {
                continue;
            }
            $content['href'] = '/lid-worden';
            DB::table('blocks')->where('id', $block->id)->update([
                'content' => json_encode($content),
                'updated_at' => now(),
            ]);
        }
    }
};
