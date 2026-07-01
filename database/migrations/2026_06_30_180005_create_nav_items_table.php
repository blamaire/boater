<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nav_items', function (Blueprint $table) {
            $table->id();
            $table->string('menu')->default('main');
            $table->foreignId('page_id')->nullable()->constrained('pages')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('nav_items')->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('href')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('visible')->default(true);
            $table->timestamps();

            $table->index(['menu', 'parent_id', 'sort_order']);
        });
    }
};
