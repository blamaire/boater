<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Snapshot van de BTW-code die gold toen de post ontstond (tarieven
        // kunnen later wijzigen; een creditering moet met hetzelfde tarief blijven rekenen).
        Schema::table('charges', function (Blueprint $table) {
            $table->foreignId('btw_code_id')->nullable()->after('product_id')->constrained('btw_codes')->nullOnDelete();
        });
    }
};
