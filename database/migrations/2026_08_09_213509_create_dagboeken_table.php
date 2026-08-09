<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Dagboeken: Verkoop/Inkoop/Memoriaal singleton, Bank/Kas meerdere mogelijk.
        Schema::create('dagboeken', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('name');
            $table->timestamps();
        });
    }
};
