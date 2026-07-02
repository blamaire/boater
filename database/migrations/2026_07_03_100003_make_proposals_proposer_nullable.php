<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // §19: publieke "Lid worden"-aanvragen komen zonder ingelogde Person binnen.
        // proposed_by_person_id moet daarom optioneel worden zodat de aanvraag als
        // Proposal geregistreerd kan worden voordat de aanmelder een Person heeft.
        Schema::table('proposals', function (Blueprint $table) {
            $table->foreignId('proposed_by_person_id')->nullable()->change();
        });
    }
};
