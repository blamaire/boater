<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // §23.3 JOURNAL_ENTRY — kop van een journaalpost (lichte dubbele boekhouding).
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('description');
            // Herkomstverwijzing, bijv. "charge:12".
            $table->string('reference')->nullable();
            $table->timestamps();

            $table->index('reference');
        });
    }
};
