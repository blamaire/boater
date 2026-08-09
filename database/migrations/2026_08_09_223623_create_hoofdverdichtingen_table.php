<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Hoogste groeperingsniveau boven het grootboek (RGS-achtig):
        // hoofdverdichting > verdichting > grootboekrekening.
        Schema::create('hoofdverdichtingen', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->timestamps();
        });
    }
};
