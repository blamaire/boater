<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('persons', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name_prefix')->nullable();
            $table->string('last_name');
            $table->date('date_of_birth')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->foreignId('household_id')->nullable()->constrained('households')->nullOnDelete();
            $table->foreignId('account_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('ecaptain_id')->nullable()->unique();
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }
};
