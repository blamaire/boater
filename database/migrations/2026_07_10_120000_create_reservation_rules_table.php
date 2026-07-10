<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // §18.3 RESERVATION_RULE. Drempels zijn additief: alle toepasselijke
        // regels (van deze categorie en van elke bovenliggende) worden gewogen.
        // Overschrijding blokkeert niet, maar routeert de aanvraag via de
        // goedkeuringsmotor (§18.4).
        Schema::create('reservation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('object_category_id')->constrained()->cascadeOnDelete();
            // 'gelijktijdig' = max N reserveringen die op hetzelfde moment lopen
            // 'per_dag'      = max N reserveringen die op dezelfde kalenderdag starten
            // 'duur'         = max N minuten totale duur van één reservering
            $table->string('constraint_type');
            $table->integer('limit_value');
            // true  = drempel geldt per persoon (bv. "één boot gelijktijdig per lid")
            // false = drempel geldt over alle reserveringen samen
            $table->boolean('per_person')->default(false);
            $table->timestamps();

            $table->index(['object_category_id', 'constraint_type']);
        });
    }
};
