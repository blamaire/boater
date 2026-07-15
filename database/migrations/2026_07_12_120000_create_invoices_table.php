<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // §23.3 INVOICE — bundelt de openstaande posten van één betaler.
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number')->nullable()->unique();
            $table->foreignId('debtor_person_id')->constrained('persons')->cascadeOnDelete();
            $table->string('status')->default('concept');
            $table->date('issued_at')->nullable();
            $table->date('due_at')->nullable();
            $table->decimal('total', 10, 2)->default(0);
            $table->timestamps();

            $table->index(['debtor_person_id', 'status']);
        });
    }
};
