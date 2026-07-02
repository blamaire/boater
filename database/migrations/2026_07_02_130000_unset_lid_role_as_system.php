<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // "Lid" is niet langer een systeem-rol; per lidmaatschapstype worden
        // eigen rollen ingericht (§6-7, Fase 2 Lidmaatschap). Bestaande
        // toewijzingen aan "Lid" blijven werken, alleen het slot gaat eraf.
        DB::table('roles')->where('name', 'Lid')->update(['is_system' => false]);
    }
};
