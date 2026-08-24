<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_pages', function (Blueprint $table) {
            $table->id();
            // Eigen CMS-infopagina (banden/blokken, via de goedkeuringsmotor
            // bewerkbaar); verwijderen van de pagina verwijdert het event.
            $table->foreignId('page_id')->unique()->constrained('pages')->cascadeOnDelete();
            $table->foreignId('created_by_person_id')->nullable()->constrained('persons')->nullOnDelete();
            $table->timestamps();
        });
    }
};
