<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('persons');
            // Eén waarde in v1 ('nieuwsbrief') — generiek genoeg voor meer
            // categorieën later (§24.3). Afwezigheid van een rij = niet
            // opted-in (conservatieve AVG-default).
            $table->string('category');
            $table->boolean('opted_in')->default(false);
            $table->timestamps();
            $table->unique(['person_id', 'category']);
        });
    }
};
