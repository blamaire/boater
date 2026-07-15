<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('object_categories', function (Blueprint $table) {
            // §18.3: hiërarchie op objectcategorieën. Nu al nodig voor
            // §22.4 (overerving van CATEGORY_RESPONSIBLE), later ook voor
            // reserveringsregels die op subcategorieën doorwerken.
            $table->foreignId('parent_id')
                ->nullable()
                ->after('slug')
                ->constrained('object_categories')
                ->nullOnDelete();
        });
    }
};
