<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Het antwoord van een inschrijver op één extra inschrijfveld
        // (vgl. het ontworpen ENROLLMENT_OPTION). Precies één van
        // text_value/option_id/count_value is gevuld, afhankelijk van het
        // veldtype.
        Schema::create('enrollment_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('field_id')->constrained('activity_registration_fields')->cascadeOnDelete();
            $table->text('text_value')->nullable();
            $table->foreignId('option_id')->nullable()->constrained('activity_registration_field_options')->nullOnDelete();
            $table->unsignedInteger('count_value')->nullable();
            $table->timestamps();

            $table->unique(['enrollment_id', 'field_id'], 'enrollment_field_unique');
        });
    }
};
