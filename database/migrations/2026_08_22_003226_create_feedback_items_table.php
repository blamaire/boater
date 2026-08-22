<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabelnaam expliciet gekozen (niet "feedback"/"feedbacks"): Eloquent's
        // automatische pluralisatie van "Feedback" is onvoorspelbaar, zelfde reden
        // als bij Dagboek::$table.
        Schema::create('feedback_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            $table->string('category');
            $table->text('message');
            $table->string('url');
            $table->string('app_version')->nullable();
            // nullOnDelete: een verwijderde pagina/versie mag de terugkoppeling
            // over die pagina niet meenemen — die blijft leesbaar, alleen zonder
            // koppeling.
            $table->foreignId('page_id')->nullable()->constrained('pages')->nullOnDelete();
            $table->foreignId('page_version_id')->nullable()->constrained('page_versions')->nullOnDelete();
            $table->string('status')->default('nieuw');
            $table->timestamps();
            $table->index('status');
        });
    }
};
