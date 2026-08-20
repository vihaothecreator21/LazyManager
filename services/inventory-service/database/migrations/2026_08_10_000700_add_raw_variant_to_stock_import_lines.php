<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_import_lines', function (Blueprint $table): void {
            $table->string('raw_variant')->nullable()->after('raw_product_name');
        });
    }

    public function down(): void
    {
        Schema::table('stock_import_lines', function (Blueprint $table): void {
            $table->dropColumn('raw_variant');
        });
    }
};
