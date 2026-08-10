<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_versions', function (Blueprint $table) {
            $table->text('meta_description')->nullable()->after('created_by_person_id');
            $table->string('og_title')->nullable()->after('meta_description');
            $table->text('og_description')->nullable()->after('og_title');
            $table->foreignId('og_image_media_asset_id')->nullable()->after('og_description')
                ->constrained('media_assets')->nullOnDelete();
        });
    }
};
