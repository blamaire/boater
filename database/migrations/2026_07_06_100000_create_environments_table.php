<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Externe RZVG-omgevingen (test, acceptatie, productie) waarnaar
        // vanuit deze omgeving pagina's kunnen worden gekopieerd.
        Schema::create('environments', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('url');
            // Token wordt encrypted opgeslagen via het cast in het model.
            $table->text('api_token');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
};
