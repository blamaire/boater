<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // De uniqueness (parent_id, slug) blokkeerde dat er tegelijk een
        // systeempagina en een gewone content-pagina met dezelfde slug bestaan.
        // Met type in de sleutel kan een 'home' als type=system op / staan, en
        // een 'home' als type=content op /pagina/home naast elkaar.
        // De FK op parent_id gebruikt de oude uniqueness-index als backing.
        // Voeg eerst de nieuwe unique toe, drop dan pas de oude — anders faalt
        // MySQL met "Cannot drop index needed in a foreign key constraint".
        Schema::table('pages', function (Blueprint $table) {
            $table->unique(['parent_id', 'slug', 'type']);
        });
        Schema::table('pages', function (Blueprint $table) {
            $table->dropUnique(['parent_id', 'slug']);
        });
    }
};
