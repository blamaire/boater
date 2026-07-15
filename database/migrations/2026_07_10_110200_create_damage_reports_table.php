<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // §22.3 — DAMAGE_REPORT. Statusworkflow is een eigen enum (geen
        // ProposalEngine, zie §22.2). Het `reporter_marked_unusable`-vinkje
        // zet het object direct op buiten_gebruik (§22.4, omkeerbaar door
        // een behandelaar).
        Schema::create('damage_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservable_object_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reported_by_person_id')->constrained('persons');
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description');
            $table->string('severity');
            $table->boolean('reporter_marked_unusable')->default(false);
            $table->string('status')->default('gemeld');
            $table->timestamp('reported_at');
            $table->foreignId('assigned_to_person_id')->nullable()->constrained('persons');
            $table->text('resolution')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('reservable_object_id');
        });
    }
};
