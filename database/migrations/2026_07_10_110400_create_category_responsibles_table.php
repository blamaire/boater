<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // §22.3 — CATEGORY_RESPONSIBLE. Meerdere personen per categorie
        // mogelijk; §22.4 zegt overerven naar bovenliggende categorie als
        // een categorie zelf geen verantwoordelijke heeft.
        Schema::create('category_responsibles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('object_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['object_category_id', 'person_id']);
        });
    }
};
