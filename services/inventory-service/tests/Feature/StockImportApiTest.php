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

    public function test_csv_parser_accepts_template_and_normalizes_sku_code(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'stock-import-');
        file_put_contents($path, "sku_code,quantity\n ao-thun-m ,12\n");

        $rows = app(\App\Infrastructure\CsvStockImportParser::class)->parse($path);

        self::assertSame([
            [
                'row_number' => 2,
                'raw_sku_code' => ' ao-thun-m ',
                'raw_quantity' => '12',
                'sku_code' => 'AO-THUN-M',
                'quantity' => 12,
                'error_message' => null,
            ],
        ], $rows);
    }

    public function test_csv_parser_rejects_wrong_header(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'stock-import-');
        file_put_contents($path, "code,qty\nAO-THUN-M,12\n");

        $this->expectException(\App\Domain\Exceptions\InventoryBusinessException::class);
        $this->expectExceptionMessage('File CSV phải có đúng hai cột sku_code và quantity.');

        app(\App\Infrastructure\CsvStockImportParser::class)->parse($path);
    }

    public function test_csv_parser_marks_invalid_quantity_rows(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'stock-import-');
        file_put_contents($path, "sku_code,quantity\nAO-THUN-M,-1\nAO-THUN-L,abc\n");

        $rows = app(\App\Infrastructure\CsvStockImportParser::class)->parse($path);

        self::assertSame('Số lượng không được âm.', $rows[0]['error_message']);
        self::assertSame('-1', $rows[0]['raw_quantity']);
        self::assertSame('Số lượng phải là số nguyên.', $rows[1]['error_message']);
        self::assertSame('abc', $rows[1]['raw_quantity']);
    }
}
