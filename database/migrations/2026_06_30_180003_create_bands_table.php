<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_version_id')->constrained('page_versions')->cascadeOnDelete();
            $table->string('zone');
            $table->unsignedTinyInteger('layout')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['page_version_id', 'sort_order']);
        });
    }
};
