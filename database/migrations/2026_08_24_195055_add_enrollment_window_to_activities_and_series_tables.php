<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fase B: inschrijfvenster en annuleringsdeadline, los van elkaar en
        // los van het publicatievenster — allebei optioneel en per voorkomen
        // instelbaar (§17.4).
        Schema::table('activities', function (Blueprint $table) {
            $table->dateTime('enrollment_opens_at')->nullable()->after('publish_until');
            $table->dateTime('enrollment_closes_at')->nullable()->after('enrollment_opens_at');
            $table->dateTime('cancellation_deadline')->nullable()->after('enrollment_closes_at');
        });

        Schema::table('activity_series', function (Blueprint $table) {
            $table->dateTime('enrollment_opens_at')->nullable()->after('publish_until');
            $table->dateTime('enrollment_closes_at')->nullable()->after('enrollment_opens_at');
            $table->dateTime('cancellation_deadline')->nullable()->after('enrollment_closes_at');
        });
    }
};
