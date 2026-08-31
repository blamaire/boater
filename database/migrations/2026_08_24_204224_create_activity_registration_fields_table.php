<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fase C: extra inschrijfvelden per voorkomen (§17.3/17.4, vgl. het
        // ontworpen ACTIVITY_OPTION). Prijzen zijn hier nog indicatief — de
        // koppeling met Product/Charge/Invoice volgt in Fase D.
        Schema::create('activity_registration_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            // 'text' | 'choice' | 'count'.
            $table->string('type');
            $table->string('label');
            $table->boolean('required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            // Alleen bij 'count': prijs per stuk. 'choice' heeft prijzen per
            // optie (zie activity_registration_field_options); 'text' heeft
            // geen prijs.
            $table->decimal('price_per_unit', 8, 2)->nullable();
            $table->unsignedInteger('max_count')->nullable();
            $table->timestamps();
        });
    }
};
