<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // §23.3 LEDGER_ACCOUNT — grootboekrekeningen voor de lichte dubbele
        // boekhouding. Producten verwijzen hiernaar voor hun opbrengstrekening.
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type');
            $table->timestamps();
        });
    }
};
