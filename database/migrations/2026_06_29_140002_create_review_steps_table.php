<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained('proposals')->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->string('assignee_type');
            $table->unsignedBigInteger('assignee_id');
            $table->string('status')->default('pending');
            $table->foreignId('decided_by_person_id')->nullable()->constrained('persons')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamps();

            $table->unique(['proposal_id', 'sequence']);
            $table->index(['assignee_type', 'assignee_id', 'status']);
        });
    }
};
