<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wordpress_import_items', function (Blueprint $table) {
            $table->unsignedBigInteger('wordpress_parent_id')->nullable()->after('wordpress_id');
        });
    }
};
