<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wordpress_import_media_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wordpress_import_item_id')->constrained('wordpress_import_items')->cascadeOnDelete();
            $table->unsignedBigInteger('wordpress_id')->unique();
            $table->string('title');
            $table->text('url');
            $table->string('mime_type')->nullable();
            $table->boolean('selected')->default(true);
            $table->foreignId('media_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->text('download_error')->nullable();
            $table->timestamps();
        });
    }
};
