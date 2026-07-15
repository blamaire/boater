<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // §23.3 CHARGE — te factureren post. Ontstaat los van een factuur en
        // wordt later gebundeld (invoice_id blijft leeg tot facturering).
        Schema::create('charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('debtor_person_id')->constrained('persons')->cascadeOnDelete();
            // Herkomst van de post (bijv. lidmaatschap, activiteitsinschrijving).
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('description');
            $table->decimal('amount', 10, 2);
            $table->string('period')->nullable();
            $table->string('status')->default('open');
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->date('due_at')->nullable();
            $table->timestamps();

            $table->index(['debtor_person_id', 'status']);
            $table->index(['subject_type', 'subject_id']);
        });
    }
};
