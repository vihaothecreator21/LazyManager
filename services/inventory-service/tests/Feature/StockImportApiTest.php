<?php

namespace Tests\Feature;

use App\Domain\Enums\StockImportStatus;
use App\Models\Product;
use App\Models\ProductSku;
use App\Models\StockImport;
use App\Models\StockImportLine;
use Illuminate\Support\Facades\Schema;

final class StockImportApiTest extends InventoryFeatureTestCase
{
    public function test_stock_import_tables_exist(): void
    {
        self::assertTrue(Schema::hasTable('stock_imports'));
        self::assertTrue(Schema::hasTable('stock_import_lines'));
        self::assertTrue(Schema::hasColumns('stock_imports', [
            'file_name',
            'file_hash',
            'status',
            'confirmed_at',
            'created_by',
        ]));
        self::assertTrue(Schema::hasColumns('stock_import_lines', [
            'stock_import_id',
            'row_number',
            'sku_code',
            'sku_id',
            'raw_sku_code',
            'raw_quantity',
            'quantity',
            'quantity_before',
            'quantity_after',
            'error_message',
        ]));
    }

    public function test_stock_import_model_relationships_work(): void
    {
        $product = Product::query()->create([
            'product_code' => 'AO-THUN',
            'name' => 'Áo thun',
            'active' => true,
        ]);
        $sku = ProductSku::query()->create([
            'product_id' => $product->id,
            'sku_code' => 'AO-THUN-M',
            'size' => 'M',
            'active' => true,
        ]);
        $stockImport = StockImport::query()->create([
            'file_name' => 'inventory.csv',
            'file_hash' => str_repeat('a', 64),
            'status' => StockImportStatus::Previewed->value,
            'created_by' => 10,
        ]);
        StockImportLine::query()->create([
            'stock_import_id' => $stockImport->id,
            'row_number' => 2,
            'sku_code' => 'AO-THUN-M',
            'raw_sku_code' => ' ao-thun-m ',
            'raw_quantity' => '12',
            'sku_id' => $sku->id,
            'quantity' => 12,
            'quantity_before' => 5,
            'quantity_after' => 12,
        ]);

        self::assertSame('AO-THUN-M', $stockImport->lines()->first()->sku->sku_code);
        self::assertSame(StockImportStatus::Previewed, $stockImport->status);
    }
}
