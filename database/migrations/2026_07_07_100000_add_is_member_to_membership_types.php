<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_types', function (Blueprint $table) {
            // Onderscheid tussen "echte" leden (contributie, ledenrechten) en
            // administratieve personen zoals ouders/verzorgers of externe
            // functionarissen die wel een Person-record hebben maar geen lid
            // zijn. Fase 3 (facturatie) en Fase 4 (reserveren) filteren hierop.
            $table->boolean('is_member')->default(true)->after('name');
        });
    }
};
