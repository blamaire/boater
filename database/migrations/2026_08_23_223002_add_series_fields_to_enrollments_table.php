<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->foreignId('series_id')->nullable()
                ->after('activity_id')
                ->constrained('activity_series')->nullOnDelete();
            // 'bundel' | 'reeks' — hoe deze inschrijving tot stand kwam.
            // Bij 'reeks' hoort er per voorkomen van de groep een eigen rij
            // (capaciteit/wachtlijst blijft per voorkomen bewaakt, §17.4),
            // maar ze delen series_id zodat ze samen af te melden zijn.
            $table->string('level')->default('bundel')->after('series_id');
        });
    }
};
