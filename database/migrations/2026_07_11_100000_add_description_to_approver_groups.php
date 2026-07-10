<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approver_groups', function (Blueprint $table) {
            // Helpt beheerders bij het onderhouden van de groepen: waarvoor
            // is deze groep bedoeld? Zichtbaar in `/beheer/goedkeuringsgroepen`.
            $table->text('description')->nullable()->after('name');
        });
    }
};
