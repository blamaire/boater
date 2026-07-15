<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_assets', function (Blueprint $table) {
            // Bepaalt of een asset in de mediabibliotheek verschijnt.
            // null = bibliotheek (standaard), anders = context-gebonden
            // (bv. 'damage_report' — schadefoto's blijven buiten `/beheer/media`).
            $table->string('context')->nullable()->after('visibility');
            $table->index('context');
        });
    }
};
