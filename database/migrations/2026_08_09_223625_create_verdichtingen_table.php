<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verdichtingen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hoofdverdichting_id')->constrained('hoofdverdichtingen')->restrictOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->timestamps();
        });
    }
};
