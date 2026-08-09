<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nullable: bestaande/nieuwe rekeningen hoeven niet meteen ingedeeld te zijn.
        Schema::table('ledger_accounts', function (Blueprint $table) {
            $table->foreignId('verdichting_id')->nullable()->after('type')->constrained('verdichtingen')->nullOnDelete();
        });
    }
};
