<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('pages')->cascadeOnDelete();
            $table->unsignedInteger('version_no');
            $table->string('status')->default('concept');
            $table->foreignId('base_version_id')->nullable()->constrained('page_versions')->nullOnDelete();
            $table->foreignId('created_by_person_id')->nullable()->constrained('persons')->nullOnDelete();
            $table->timestamps();

            $table->unique(['page_id', 'version_no']);
            $table->index(['page_id', 'status']);
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->foreign('published_version_id')
                ->references('id')
                ->on('page_versions')
                ->nullOnDelete();
        });
    }
};
