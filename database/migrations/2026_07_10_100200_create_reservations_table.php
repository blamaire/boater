<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservable_object_id')->constrained('reservable_objects')->cascadeOnDelete();
            // Voor wie de reservering is (begunstigde).
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            // Wie de reservering heeft aangevraagd (kan zelfde zijn als
            // person_id, of iemand die gemachtigd is via person_relations).
            $table->foreignId('requested_by_person_id')->nullable()->constrained('persons')->nullOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            // 'bevestigd' | 'geannuleerd' (§18.4 — v1 kent geen review; drempels
            // en 'in_review' komen in v2 met de reserveringsregelmotor).
            $table->string('status')->default('bevestigd');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['reservable_object_id', 'starts_at', 'ends_at']);
            $table->index(['person_id', 'starts_at']);
            $table->index(['status', 'starts_at']);
        });
    }
};
