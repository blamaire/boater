<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pivot: schadefoto's zijn `MediaAsset`-records met `context='damage_report'`
        // zodat ze buiten de mediabibliotheek blijven, maar wel gedeelde
        // opslag/URL-logica gebruiken (§22.5, koppeling met Media).
        Schema::create('damage_report_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('damage_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_asset_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['damage_report_id', 'media_asset_id']);
        });
    }
};
