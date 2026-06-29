<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('subject_type');
            $table->json('condition')->nullable();
            $table->boolean('auto_apply')->default(false);
            $table->json('steps')->nullable();
            $table->string('bypass_permission')->nullable();
            $table->string('resubmit_behavior')->default('restart');
            $table->unsignedSmallInteger('reminder_after_days')->nullable();
            $table->unsignedSmallInteger('escalation_after_days')->nullable();
            $table->foreignId('escalation_group_id')->nullable()->constrained('approver_groups')->nullOnDelete();
            $table->timestamps();

            $table->index('subject_type');
        });
    }
};
