<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fase D: standaard- en annuleringskosten als losse, optionele
        // Product-koppelingen (§23.5) — het bedrag komt uit de actuele
        // ProductPrice van het gekoppelde product, niet uit een los bedrag
        // hier, zodat prijshistorie/BTW-routing via het bestaande
        // boekhoudkundige domein blijft lopen.
        Schema::table('activities', function (Blueprint $table) {
            $table->foreignId('standard_cost_product_id')->nullable()->after('cancellation_deadline')->constrained('products')->nullOnDelete();
            $table->foreignId('cancellation_cost_product_id')->nullable()->after('standard_cost_product_id')->constrained('products')->nullOnDelete();
        });

        Schema::table('activity_series', function (Blueprint $table) {
            $table->foreignId('standard_cost_product_id')->nullable()->after('cancellation_deadline')->constrained('products')->nullOnDelete();
            $table->foreignId('cancellation_cost_product_id')->nullable()->after('standard_cost_product_id')->constrained('products')->nullOnDelete();
        });
    }
};
