<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_category_id')->constrained('activity_categories')->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->string('location')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            // 'public' | 'members' | 'staff' — bepaalt wie de activiteit ziet
            // en zich mag inschrijven. Zelfde vocabulaire als CMS-pagina's (§5).
            $table->string('visibility')->default('members');
            // 'concept' | 'gepubliceerd' | 'afgelast'
            $table->string('status')->default('gepubliceerd');
            $table->foreignId('created_by_person_id')->nullable()->constrained('persons')->nullOnDelete();
            $table->timestamps();

            $table->index('starts_at');
            $table->index(['status', 'starts_at']);
        });
    }
};
