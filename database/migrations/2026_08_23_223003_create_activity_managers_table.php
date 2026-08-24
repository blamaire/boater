<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Gedelegeerd beheer op objectniveau (§6, aangekondigd als latere
        // uitbreiding): een beheerder ziet inschrijvingen en mag de
        // activiteit wijzigen zonder de globale `activities.update`-permissie.
        Schema::create('activity_managers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            $table->boolean('notify')->default(true);
            $table->timestamps();

            $table->unique(['activity_id', 'person_id']);
        });
    }
};
