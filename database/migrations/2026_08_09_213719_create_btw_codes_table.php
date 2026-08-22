<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Eén code dekt desgewenst beide richtingen tegelijk: hetzelfde
        // percentage boekt bij verkoop op een andere rekening dan bij inkoop.
        Schema::create('btw_codes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('percentage', 5, 2);
            $table->foreignId('af_te_dragen_ledger_account_id')->nullable()->constrained('ledger_accounts')->restrictOnDelete();
            $table->foreignId('voor_te_vorderen_ledger_account_id')->nullable()->constrained('ledger_accounts')->restrictOnDelete();
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->timestamps();
        });
    }
};
