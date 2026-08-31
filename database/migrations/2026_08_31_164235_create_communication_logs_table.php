<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_logs', function (Blueprint $table) {
            $table->id();
            // Nullable: niet elke ontvanger heeft al een Person (bv. een
            // "Lid worden"-bevestiging vóór de aanvraag is goedgekeurd) —
            // dan blijft het opgegeven e-mailadres de enige identificatie.
            $table->foreignId('person_id')->nullable()->constrained('persons')->nullOnDelete();
            $table->string('email')->nullable();
            $table->string('channel');
            $table->string('direction');
            $table->string('subject');
            $table->text('notes')->nullable();
            // Null = automatisch/systeem verstuurd (§30.1); gevuld bij een
            // toekomstige handmatig-vastgelegd-contactmoment.
            $table->foreignId('logged_by_person_id')->nullable()->constrained('persons')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->nullableMorphs('related');
            $table->timestamps();
            $table->index('person_id');
        });
    }
};
