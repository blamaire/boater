<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Voor account-deactivering door een beheerder. Gebruiken we boven
            // soft-delete omdat we het account als record willen behouden
            // (audit, historische rollen, e-mails) maar de login weigeren.
            $table->timestamp('disabled_at')->nullable()->after('remember_token');
        });
    }
};
