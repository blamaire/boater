<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per lid instelbaar of een bepaald persoonsgegeven zichtbaar is voor andere
 * leden (§19.4). Standaard verborgen — privacy-first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('person_field_visibilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            $table->string('field_key');
            $table->boolean('visible_to_members')->default(false);
            $table->timestamps();

            $table->unique(['person_id', 'field_key']);
        });
    }
};
