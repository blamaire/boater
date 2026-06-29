<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('person_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('active');
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->unique(['person_id', 'permission_id']);
        });
    }
};
