<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_assets', function (Blueprint $table) {
            // Portable identifier zodat één en dezelfde asset op meerdere
            // omgevingen (localhost/test/acc/prod) herkenbaar is. Bij push naar
            // een andere omgeving gebruiken we de UUID (niet de lokale ID) om
            // te matchen. Bestaande records krijgen bij deze migratie een verse
            // UUID — hetgeen betekent dat asset X op ACC/test alleen matcht als
            // ze na deze migratie via een push zijn aangekomen.
            $table->uuid('uuid')->nullable()->after('id');
        });

        // Backfill: geef elk bestaand record een verse UUID.
        DB::table('media_assets')->orderBy('id')->lazy()->each(function ($row): void {
            DB::table('media_assets')
                ->where('id', $row->id)
                ->update(['uuid' => (string) Str::uuid()]);
        });

        // Nu verplicht maken en uniek indexeren.
        Schema::table('media_assets', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
            $table->unique('uuid');
        });
    }
};
