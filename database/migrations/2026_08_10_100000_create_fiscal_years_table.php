<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Boekjaar = altijd kalenderjaar (jan-dec), geen instelbare startmaand.
        Schema::create('fiscal_years', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->timestamps();
        });
    }
};
