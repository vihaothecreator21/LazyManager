<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_sales', function (Blueprint $table): void {
            $table->id();
            $table->date('sales_date');
            $table->date('confirmed_sales_date')->nullable()->unique();
            $table->string('file_name');
            $table->string('file_hash', 64);
            $table->string('status', 32)->default('DRAFT');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->timestamps();
            $table->index('sales_date');
            $table->index('status');
            $table->index('created_at');
        });

        Schema::create('daily_sale_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('daily_sale_id')->constrained('daily_sales')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('sku_code', 64);
            $table->foreignId('sku_id')->nullable()->constrained('product_skus')->restrictOnDelete();
            $table->string('raw_sku_code')->nullable();
            $table->string('raw_quantity_sold')->nullable();
            $table->unsignedInteger('quantity_sold')->nullable();
            $table->integer('preview_quantity_before')->nullable();
            $table->integer('preview_quantity_after')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamps();
            $table->unique(['daily_sale_id', 'row_number']);
            $table->index('daily_sale_id');
            $table->index('sku_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_sale_lines');
        Schema::dropIfExists('daily_sales');
    }
};
