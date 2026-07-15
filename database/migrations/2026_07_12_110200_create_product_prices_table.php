<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // §23.3 PRODUCT_PRICE — prijshistorie. De geldende prijs op een datum is
        // die met de hoogste `valid_from` <= die datum (bijv. per verenigingsjaar).
        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->date('valid_from');
            $table->decimal('amount', 10, 2);
            $table->timestamps();

            $table->unique(['product_id', 'valid_from']);
        });
    }
};
