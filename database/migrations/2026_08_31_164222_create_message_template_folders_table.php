<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_template_folders', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('message_template_folders');
            // 'Systeemberichten'/'Mailings' — vaste root-mappen, niet aan te
            // maken/hernoemen/verwijderen door een beheerder (§24.4).
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });
    }
};
