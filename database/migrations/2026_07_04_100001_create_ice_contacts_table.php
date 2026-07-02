<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ICE-contacten (In Case of Emergency) per persoon. Elk lid mag eigen
 * ICE-contacten beheren zonder aparte permissie; vrijwilligers met de
 * permissie ice_contacts.view kunnen ze tijdens activiteiten opzoeken.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ice_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            $table->string('name');
            $table->string('relation');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('person_id');
        });
    }
};
