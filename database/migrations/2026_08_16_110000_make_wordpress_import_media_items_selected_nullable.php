<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wordpress_import_media_items', function (Blueprint $table) {
            $table->boolean('selected')->nullable()->default(null)->change();
        });

        // Bestaande, nog niet gedownloade rijen stonden op true via de oude
        // default; die tellen nu als "onbeslist" onder de nieuwe UI (twee
        // bewuste knoppen i.p.v. een vooraf aangevinkte checkbox).
        DB::table('wordpress_import_media_items')->whereNull('media_asset_id')->update(['selected' => null]);
    }
};
