<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_imports', function (Blueprint $table): void {
            $table->id();
            $table->string('file_name');
            $table->string('file_hash', 64)->unique();
            $table->string('status', 32)->default('PREVIEWED')->index();
            $table->timestamp('confirmed_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index('created_at');
        });

        Schema::create('stock_import_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_import_id')->constrained('stock_imports')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('sku_code', 64);
            $table->foreignId('sku_id')->nullable()->constrained('product_skus')->nullOnDelete();
            $table->string('raw_sku_code')->nullable();
            $table->string('raw_quantity')->nullable();
            $table->integer('quantity')->nullable();
            $table->integer('quantity_before')->nullable();
            $table->integer('quantity_after')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamps();
            $table->unique(['stock_import_id', 'row_number']);
            $table->index('sku_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_import_lines');
        Schema::dropIfExists('stock_imports');
    }
};
