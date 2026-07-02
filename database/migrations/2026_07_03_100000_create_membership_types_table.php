<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_types', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();

            $table->unsignedTinyInteger('min_age')->nullable();
            $table->unsignedTinyInteger('max_age')->nullable();

            $table->boolean('allows_boat_use')->default(false);
            $table->boolean('allows_instruction')->default(false);
            $table->unsignedTinyInteger('intro_per_year')->default(0);
            $table->boolean('allows_competition')->default(true);
            $table->boolean('seasonal_only')->default(false);
            $table->boolean('auto_expiry')->default(false);
            $table->boolean('requires_proof')->default(false);

            $table->boolean('is_partner_type')->default(false);
            $table->string('derives_from_key')->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }
};
