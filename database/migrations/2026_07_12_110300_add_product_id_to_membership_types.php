<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // §23.4 MEMBERSHIP_TYPE.product_id — een lidmaatschapsvorm verwijst naar
        // zijn contributie-product. Leeg tot een product is gekoppeld.
        Schema::table('membership_types', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('key')->constrained()->nullOnDelete();
        });
    }
};
