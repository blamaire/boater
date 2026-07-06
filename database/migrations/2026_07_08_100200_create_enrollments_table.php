<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained('activities')->cascadeOnDelete();
            // Begunstigde — degene die daadwerkelijk meedoet.
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            // Inschrijver — kan een ander zijn dan de begunstigde (ouder voor
            // jeugdlid, of beheerder die iemand handmatig inschrijft).
            $table->foreignId('requested_by_person_id')->nullable()->constrained('persons')->nullOnDelete();
            // 'aangemeld' | 'wachtlijst' | 'afgemeld'
            $table->string('status')->default('aangemeld');
            $table->dateTime('enrolled_at');
            $table->timestamps();

            // Eén persoon kan niet twee actieve inschrijvingen op dezelfde
            // activiteit hebben — voorkom dubbele plek-bezetting.
            $table->unique(['activity_id', 'person_id']);
        });
    }
};
