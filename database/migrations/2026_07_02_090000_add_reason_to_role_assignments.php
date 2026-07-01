<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Voegt een optioneel motiveringsveld toe aan role_assignments,
// zodat beheerders bij toewijzing een korte reden kunnen vastleggen.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('role_assignments', function (Blueprint $table) {
            $table->string('reason', 500)->nullable()->after('deactivated_at');
        });
    }
};
