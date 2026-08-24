<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Een hele ApproverGroup als gedelegeerd beheerder koppelen: alle
        // huidige én toekomstige leden van de groep gelden als beheerder,
        // naast de losse personen in `activity_managers`.
        Schema::create('activity_manager_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approver_group_id')->constrained()->cascadeOnDelete();
            $table->boolean('notify')->default(true);
            $table->timestamps();

            $table->unique(['activity_id', 'approver_group_id']);
        });
    }
};
