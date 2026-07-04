<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Singleton-tabel met de beheersbare site-brede gegevens: het
        // contactblok voor de footer, sociale-media-URL's en verwijzingen
        // naar CMS-pagina's voor disclaimer/AVG-links.
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('contact_name')->nullable();
            $table->text('contact_address')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('facebook_url')->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('youtube_url')->nullable();
            $table->foreignId('privacy_page_id')->nullable()->constrained('pages')->nullOnDelete();
            $table->foreignId('terms_page_id')->nullable()->constrained('pages')->nullOnDelete();
            $table->timestamps();
        });

        // Zorg voor het singleton-record.
        DB::table('site_settings')->insert([
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
