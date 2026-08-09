<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // §23.6 B3: cumulatief gecrediteerd bedrag van een post (basis-creditnota's).
        Schema::table('charges', function (Blueprint $table) {
            $table->decimal('credited_amount', 10, 2)->default(0)->after('amount');
        });
    }
};
