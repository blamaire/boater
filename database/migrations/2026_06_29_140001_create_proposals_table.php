<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposals', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('change_type');
            $table->json('payload');
            $table->foreignId('proposed_by_person_id')->constrained('persons')->cascadeOnDelete();
            $table->string('status');
            $table->foreignId('policy_id')->nullable()->constrained('review_policies')->nullOnDelete();
            $table->unsignedSmallInteger('current_step')->default(0);
            $table->text('decision_reason')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->index('status');
            $table->index('proposed_by_person_id');
        });
    }
};
