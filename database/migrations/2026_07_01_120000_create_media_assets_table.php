<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->string('disk')->default('media');
            $table->string('path')->unique();
            $table->string('thumbnail_path')->nullable();
            $table->string('original_name');
            $table->string('mime_type');
            $table->string('type');
            $table->unsignedBigInteger('file_size');
            $table->string('alt')->nullable();
            $table->json('dimensions')->nullable();
            $table->string('visibility')->default('publiek');
            $table->foreignId('uploaded_by_person_id')->nullable()->constrained('persons')->nullOnDelete();
            $table->timestamps();

            $table->index('type');
            $table->index('visibility');
        });
    }
};
