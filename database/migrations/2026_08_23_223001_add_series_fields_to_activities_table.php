<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->foreignId('series_id')->nullable()
                ->after('activity_page_id')
                ->constrained('activity_series')->restrictOnDelete();
            // Een voorkomen dat los van de reeks is aangepast: blijft
            // beschermd tegen latere serie-brede bewerkingen (§17.4).
            $table->boolean('is_exception')->default(false)->after('series_id');
            $table->unsignedInteger('min_capacity')->nullable()->after('capacity');
            $table->unsignedInteger('min_age')->nullable()->after('min_capacity');
            $table->unsignedInteger('max_age')->nullable()->after('min_age');
            // Publicatievenster: los van starts_at/ends_at (wanneer de
            // activiteit plaatsvindt) — bepaalt wanneer ze publiek zichtbaar is.
            $table->dateTime('publish_from')->nullable()->after('max_age');
            $table->dateTime('publish_until')->nullable()->after('publish_from');
        });
    }
};
