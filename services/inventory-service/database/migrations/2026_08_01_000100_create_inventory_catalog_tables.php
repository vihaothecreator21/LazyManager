<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('product_code', 64)->unique();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index('name');
        });

        Schema::create('product_skus', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products');
            $table->string('sku_code', 64)->unique();
            $table->string('size', 64);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index('product_id');
        });

        if (DB::getDriverName() === 'sqlite') {
            $this->createSqliteInventoryTables();

            return;
        }

        Schema::create('inventory_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sku_id')->unique()->constrained('product_skus');
            $table->integer('quantity')->default(0);
            $table->timestamps();
        });

        Schema::create('inventory_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sku_id')->constrained('product_skus');
            $table->string('type', 32);
            $table->integer('quantity_change');
            $table->integer('quantity_before');
            $table->integer('quantity_after');
            $table->string('reference_type', 64)->nullable();
            $table->string('reference_id', 64)->nullable();
            $table->string('reason')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->nullable();
            $table->index('sku_id');
            $table->index('created_at');
            $table->index('type');
        });

        DB::statement('ALTER TABLE inventory_balances ADD CONSTRAINT inventory_balances_quantity_non_negative CHECK (quantity >= 0)');
        DB::statement('ALTER TABLE inventory_transactions ADD CONSTRAINT inventory_transactions_quantity_before_non_negative CHECK (quantity_before >= 0)');
        DB::statement('ALTER TABLE inventory_transactions ADD CONSTRAINT inventory_transactions_quantity_after_non_negative CHECK (quantity_after >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transactions');
        Schema::dropIfExists('inventory_balances');
        Schema::dropIfExists('product_skus');
        Schema::dropIfExists('products');
    }

    private function createSqliteInventoryTables(): void
    {
        DB::statement(
            'CREATE TABLE inventory_balances (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                sku_id INTEGER NOT NULL,
                quantity INTEGER NOT NULL DEFAULT 0,
                created_at DATETIME,
                updated_at DATETIME,
                FOREIGN KEY (sku_id) REFERENCES product_skus(id),
                CHECK (quantity >= 0)
            )',
        );
        DB::statement('CREATE UNIQUE INDEX inventory_balances_sku_id_unique ON inventory_balances(sku_id)');

        DB::statement(
            'CREATE TABLE inventory_transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                sku_id INTEGER NOT NULL,
                type VARCHAR(32) NOT NULL,
                quantity_change INTEGER NOT NULL,
                quantity_before INTEGER NOT NULL,
                quantity_after INTEGER NOT NULL,
                reference_type VARCHAR(64),
                reference_id VARCHAR(64),
                reason VARCHAR(255),
                created_by INTEGER,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME,
                FOREIGN KEY (sku_id) REFERENCES product_skus(id),
                CHECK (quantity_before >= 0),
                CHECK (quantity_after >= 0)
            )',
        );
        DB::statement('CREATE INDEX inventory_transactions_sku_id_index ON inventory_transactions(sku_id)');
        DB::statement('CREATE INDEX inventory_transactions_created_at_index ON inventory_transactions(created_at)');
        DB::statement('CREATE INDEX inventory_transactions_type_index ON inventory_transactions(type)');
    }
};
