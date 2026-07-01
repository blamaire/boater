<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bands', function (Blueprint $table) {
            $table->foreignId('origin_band_id')->nullable()->after('page_version_id')
                ->constrained('bands')->nullOnDelete();
            $table->index('origin_band_id');
        });

        Schema::table('blocks', function (Blueprint $table) {
            $table->foreignId('origin_block_id')->nullable()->after('band_id')
                ->constrained('blocks')->nullOnDelete();
            $table->index('origin_block_id');
        });
    }
};
