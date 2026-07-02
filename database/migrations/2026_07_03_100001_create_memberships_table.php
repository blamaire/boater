<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            $table->foreignId('membership_type_id')->constrained('membership_types')->restrictOnDelete();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('derives_from_membership_id')->nullable()->constrained('memberships')->nullOnDelete();
            $table->foreignId('billing_person_id')->nullable()->constrained('persons')->nullOnDelete();
            $table->timestamps();

            $table->index(['person_id', 'status']);
        });
    }
};
