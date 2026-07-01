<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug');
            $table->string('title');
            $table->string('type')->default('content');
            $table->string('visibility')->default('publiek');
            $table->foreignId('parent_id')->nullable()->constrained('pages')->nullOnDelete();
            $table->foreignId('template_id')->constrained('templates')->restrictOnDelete();
            $table->unsignedBigInteger('published_version_id')->nullable();
            $table->timestamps();

            $table->unique(['parent_id', 'slug']);
        });
    }
};
