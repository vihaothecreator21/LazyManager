<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_counts', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->date('count_date');
            $table->string('status', 32)->default('DRAFT');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index('count_date');
            $table->index('status');
            $table->index('created_at');
        });

        if (DB::getDriverName() === 'sqlite') {
            $this->createSqliteStockCountLines();

            return;
        }

        Schema::create('stock_count_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_count_id')->constrained('stock_counts')->cascadeOnDelete();
            $table->foreignId('sku_id')->constrained('product_skus')->restrictOnDelete();
            $table->string('sku_code');
            $table->unsignedBigInteger('product_id');
            $table->string('product_code');
            $table->string('product_name');
            $table->string('size', 100);
            $table->integer('expected_quantity')->default(0);
            $table->integer('actual_quantity')->nullable();
            $table->integer('variance')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->unique(['stock_count_id', 'sku_id']);
            $table->index('stock_count_id');
            $table->index('sku_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('alter table stock_count_lines add constraint stock_count_lines_expected_quantity_check check (expected_quantity >= 0)');
            DB::statement('alter table stock_count_lines add constraint stock_count_lines_actual_quantity_check check (actual_quantity is null or actual_quantity >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_count_lines');
        Schema::dropIfExists('stock_counts');
    }

    private function createSqliteStockCountLines(): void
    {
        DB::statement(
            'CREATE TABLE stock_count_lines (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                stock_count_id INTEGER NOT NULL,
                sku_id INTEGER NOT NULL,
                sku_code VARCHAR(255) NOT NULL,
                product_id INTEGER NOT NULL,
                product_code VARCHAR(255) NOT NULL,
                product_name VARCHAR(255) NOT NULL,
                size VARCHAR(100) NOT NULL,
                expected_quantity INTEGER NOT NULL DEFAULT 0,
                actual_quantity INTEGER,
                variance INTEGER,
                note VARCHAR(255),
                created_at DATETIME,
                updated_at DATETIME,
                FOREIGN KEY (stock_count_id) REFERENCES stock_counts(id) ON DELETE CASCADE,
                FOREIGN KEY (sku_id) REFERENCES product_skus(id) ON DELETE RESTRICT,
                UNIQUE (stock_count_id, sku_id),
                CHECK (expected_quantity >= 0),
                CHECK (actual_quantity IS NULL OR actual_quantity >= 0)
            )',
        );
        DB::statement('CREATE INDEX stock_count_lines_stock_count_id_index ON stock_count_lines(stock_count_id)');
        DB::statement('CREATE INDEX stock_count_lines_sku_id_index ON stock_count_lines(sku_id)');
    }
};
