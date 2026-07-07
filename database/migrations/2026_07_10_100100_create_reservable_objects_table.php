<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservable_objects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('object_category_id')->constrained('object_categories')->restrictOnDelete();
            $table->string('name');
            $table->string('location')->nullable();
            // Vrije extra attributen (bv. aantal zitplaatsen, coach-uitrusting).
            $table->json('attributes')->nullable();
            // 'beschikbaar' | 'buiten_gebruik' (§18.4 — buiten_gebruik betekent
            // niet reserveerbaar; door §22 Schade-melder gezet op onmiddellijk).
            $table->string('status')->default('beschikbaar');
            $table->timestamps();

            $table->index(['object_category_id', 'status']);
        });
    }
};
