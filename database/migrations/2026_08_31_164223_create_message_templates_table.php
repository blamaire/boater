<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            // Code verwijst hiernaar (bv. 'enrollment_confirmed') — vandaar
            // uniek en niet zomaar een auto-increment-id.
            $table->string('key')->unique();
            $table->string('name');
            $table->string('subject');
            // Array van blocks (§24, App\Enums\MessageBlockType) — geen platte
            // HTML-string. Zie App\Services\Communication\MessageBlockRenderer.
            $table->json('body');
            $table->string('type');
            $table->timestamps();
        });
    }
};
