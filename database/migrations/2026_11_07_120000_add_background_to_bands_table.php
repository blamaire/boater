<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Achtergrondkleur per band (zie App\Enums\BandBackground). Bestaande
     * banden worden 'wit', zodat bestaande pagina's er hetzelfde uit blijven zien.
     */
    public function up(): void
    {
        Schema::table('bands', function (Blueprint $table): void {
            $table->string('background', 16)->default('wit')->after('layout');
        });
    }
};
