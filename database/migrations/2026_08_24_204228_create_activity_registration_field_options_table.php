<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keuzemogelijkheden voor een 'choice'-veld, elk met een eigen
        // (indicatieve) prijs — bv. "Vega" €10 / "Vlees" €12.
        Schema::create('activity_registration_field_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_id')->constrained('activity_registration_fields')->cascadeOnDelete();
            $table->string('label');
            $table->decimal('price', 8, 2)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }
};
