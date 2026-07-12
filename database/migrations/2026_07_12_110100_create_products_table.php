<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // §23.3 PRODUCT — artikel (contributie, activiteitsbijdrage, advertentie,
        // overig). De prijs zit in PRODUCT_PRICE (prijshistorie), niet hier.
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type');
            // Opbrengstrekening; leeg toegestaan tot het rekeningschema gekoppeld is.
            $table->foreignId('ledger_account_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_recurring')->default(false);
            $table->string('recurrence')->nullable();
            $table->timestamps();

            $table->index('type');
        });
    }
};
