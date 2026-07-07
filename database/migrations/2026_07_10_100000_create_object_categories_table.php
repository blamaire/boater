<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('object_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            // Categorie voor een vaartuig: reserveerder moet dan
            // MembershipType.allows_boat_use=true hebben (§18.4-invariant).
            $table->boolean('requires_boat_right')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }
};
