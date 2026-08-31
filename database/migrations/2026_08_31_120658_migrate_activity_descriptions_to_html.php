<?php

use App\Services\Cms\BlockContentSanitizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * De omschrijving van activiteiten/reeksen werd tot nu toe als platte
     * tekst opgeslagen en met nl2br(e(...)) getoond; sinds de Trix-editor
     * wordt het veld rechtstreeks als (gesaneerde) HTML gerenderd. Zet
     * bestaande platte-tekst-omschrijvingen om zodat regeleinden behouden
     * blijven. Deze migratie draait in dezelfde release als de Trix-editor,
     * dus alle rijen die hier nog bestaan zijn per definitie platte tekst —
     * geen detectie van "is dit al HTML" nodig (en onbetrouwbaar: platte
     * tekst met bv. "<vraag na>" zou dan onterecht ongesaneerd blijven).
     */
    public function up(): void
    {
        $sanitizer = app(BlockContentSanitizer::class);

        foreach (['activities', 'activity_series'] as $table) {
            DB::table($table)
                ->whereNotNull('description')
                ->where('description', '!=', '')
                ->orderBy('id')
                ->select('id', 'description')
                ->cursor()
                ->each(function (object $row) use ($table, $sanitizer): void {
                    $html = $sanitizer->sanitizeHtml(nl2br(e($row->description)));

                    DB::table($table)->where('id', $row->id)->update(['description' => $html]);
                });
        }
    }
};
