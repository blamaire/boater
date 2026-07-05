<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Expliciete koppeling tussen twee personen (bv. ouder van jeugdlid),
        // los van huishouden of guardian-tabel. Bewust een aparte tabel omdat
        // dezelfde persoon meerdere kinderen kan hebben of meerdere rollen kan
        // vervullen (ouder van A, terrein-contact voor B) en die relaties
        // structureel doorzoekbaar moeten zijn.
        Schema::create('person_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            $table->foreignId('related_person_id')->constrained('persons')->cascadeOnDelete();
            // 'ouder_van', 'verzorger_van', later evt. 'partner_van' / 'voogd_van'.
            $table->string('type');
            $table->timestamps();

            $table->unique(['person_id', 'related_person_id', 'type'], 'person_relations_unique');
        });
    }
};
