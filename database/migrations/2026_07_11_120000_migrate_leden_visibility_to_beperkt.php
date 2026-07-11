<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Zichtbaarheids-consolidatie: de waarde `leden` verdwijnt uit
 * `PageVisibility` en wordt overal vervangen door `beperkt`.
 *
 * Semantiek verandert mee (§11): `beperkt` = ingelogd + actief lid
 * of een inzage-rol (redacteur/beheerder). Zie ontwerpdoc §11 en §26.
 *
 * Alle tabellen die de gedeelde enum gebruiken: `pages`, `blocks`
 * en `media_assets`.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['pages', 'blocks', 'media_assets'] as $table) {
            DB::table($table)
                ->where('visibility', 'leden')
                ->update(['visibility' => 'beperkt']);
        }
    }
};
