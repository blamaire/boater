<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §21.2 — FIELD_DEFINITION: centrale metadata-tabel voor
 * Person-/Membership-velden. Consolideert verbergbaar, doorzoekbaar,
 * gevoelig en de standaard-zichtbaarheid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('field_key')->unique();
            $table->string('label');
            $table->boolean('is_hideable')->default(true);
            $table->boolean('is_searchable')->default(false);
            $table->boolean('is_sensitive')->default(false);
            $table->boolean('default_visible')->default(true);
            $table->timestamps();
        });
    }
};
