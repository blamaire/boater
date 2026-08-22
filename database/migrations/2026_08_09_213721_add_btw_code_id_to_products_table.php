<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nullable: de meeste producten (bv. contributie) blijven onbelast.
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('btw_code_id')->nullable()->after('ledger_account_id')->constrained('btw_codes')->nullOnDelete();
        });
    }
};
