<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardianships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('minor_person_id')->constrained('persons')->cascadeOnDelete();
            $table->foreignId('guardian_person_id')->constrained('persons')->cascadeOnDelete();
            $table->boolean('is_payer')->default(true);
            $table->boolean('may_act_on_behalf')->default(true);
            $table->timestamp('consent_at')->nullable();
            $table->timestamps();

            $table->unique(['minor_person_id', 'guardian_person_id']);
        });
    }
};
