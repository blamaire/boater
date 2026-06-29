<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('approver_groups')->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['group_id', 'person_id']);
        });
    }
};
