<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_category_id')->constrained('activity_categories')->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->unsignedInteger('default_capacity')->nullable();
            $table->unsignedInteger('min_capacity')->nullable();
            $table->unsignedInteger('min_age')->nullable();
            $table->unsignedInteger('max_age')->nullable();
            $table->dateTime('publish_from')->nullable();
            $table->dateTime('publish_until')->nullable();
            // 'bundel' | 'reeks' — bepaalt of leden per activiteit apart aanmelden
            // (bundel) of in één keer voor alles (reeks) (§17.4).
            $table->string('enrollment_level')->default('bundel');
            $table->string('visibility')->default('members');
            $table->string('status')->default('gepubliceerd');
            // Afsplitsing via "dit en volgende" (§17.4): wijst terug naar de
            // oorspronkelijke reeks waar deze vervolgreeks vanaf is gesplitst.
            $table->foreignId('split_from_id')->nullable()->constrained('activity_series')->nullOnDelete();
            $table->foreignId('created_by_person_id')->nullable()->constrained('persons')->nullOnDelete();
            $table->timestamps();
        });
    }
};
