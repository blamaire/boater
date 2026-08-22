<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_requests', function (Blueprint $table) {
            $table->id();
            // restrictOnDelete: een onderwerp met bestaande verzoeken mag niet
            // verwijderd worden (app-level guard in ContactOnderwerpBeheer geeft
            // hierover een vriendelijke foutmelding vóórdat de database dit
            // afdwingt).
            $table->foreignId('contact_topic_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            // Beide mogen aangevinkt zijn (bv. "bel me, en mail ter bevestiging") —
            // vandaar twee losse vlaggen i.p.v. één voorkeur-enum.
            $table->boolean('contact_by_phone')->default(false);
            $table->boolean('contact_by_email')->default(false);
            $table->text('message');
            $table->string('status')->default('nieuw');
            // T.b.v. onderzoek bij misbruik/spam, naast de rate-limiter zelf.
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index('status');
        });
    }
};
