<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->foreignId('activity_page_id')->nullable()
                ->after('activity_category_id')
                ->constrained('activity_pages')->restrictOnDelete();
        });
    }
};
