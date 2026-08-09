<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Elke journaalpost hoort bij een dagboek; nooit stilzwijgend boekingsgeschiedenis verliezen.
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->foreignId('dagboek_id')->after('id')->constrained('dagboeken')->restrictOnDelete();
        });
    }
};
