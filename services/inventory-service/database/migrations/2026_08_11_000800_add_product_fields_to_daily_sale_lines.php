<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_sale_lines', function (Blueprint $table): void {
            $table->string('raw_product_name')->nullable()->after('sku_id');
            $table->string('raw_variant')->nullable()->after('raw_product_name');
        });
    }

    public function down(): void
    {
        Schema::table('daily_sale_lines', function (Blueprint $table): void {
            $table->dropColumn(['raw_product_name', 'raw_variant']);
        });
    }
};
