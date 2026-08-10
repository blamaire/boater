<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Elke journaalpost hoort bij een periode binnen een boekjaar; nodig om
        // een periode later te kunnen afsluiten/vergrendelen (§23).
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->foreignId('period_id')->after('dagboek_id')->constrained('periods')->restrictOnDelete();
        });
    }
};
