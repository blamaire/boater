<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('band_id')->constrained('bands')->cascadeOnDelete();
            $table->unsignedTinyInteger('column_index')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('type');
            $table->json('content');
            $table->string('visibility')->default('publiek');
            $table->timestamps();

            $table->index(['band_id', 'column_index', 'sort_order']);
        });
    }
};
