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
            $table->string('preferred_contact_method'); // 'bellen' | 'mailen'
            $table->text('message');
            $table->string('status')->default('nieuw');
            // T.b.v. onderzoek bij misbruik/spam, naast de rate-limiter zelf.
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index('status');
        });
    }
};
