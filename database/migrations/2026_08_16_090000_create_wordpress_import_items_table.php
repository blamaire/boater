<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wordpress_import_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wordpress_id')->unique();
            $table->string('wordpress_type');
            $table->string('title');
            $table->string('slug');
            $table->longText('content_html');
            $table->text('excerpt')->nullable();
            $table->dateTime('wordpress_published_at')->nullable();
            $table->string('status')->default('nieuw');
            // nullOnDelete i.p.v. restrictOnDelete: PageController::destroy() doet
            // een kale $page->delete() zonder koppelingscheck. Met restrictOnDelete
            // zou het verwijderen van een gewone pagina via het bestaande
            // paginabeheer een onverwachte DB-fout geven zodra die pagina ooit via
            // deze import is ontstaan.
            $table->foreignId('page_id')->nullable()->constrained('pages')->nullOnDelete();
            $table->json('raw_meta')->nullable();
            $table->timestamps();
            $table->index(['status', 'wordpress_type']);
        });
    }
};
