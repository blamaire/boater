<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_topics', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // restrictOnDelete: een onderwerp mag niet zonder verantwoordelijke
            // komen te staan (dan heeft niemand meer wie het verzoek ontvangt) —
            // een beheerder wijst eerst iemand anders toe.
            $table->foreignId('responsible_person_id')->constrained('persons')->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }
};
