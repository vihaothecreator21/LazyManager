<?php

namespace Tests\Feature;

use App\Domain\Enums\StockImportStatus;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\ProductSku;
use App\Models\StockImport;
use App\Models\StockImportLine;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\UploadedFile;

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

    public function test_preview_stock_import_creates_import_and_lines(): void
    {
        $sku = $this->createSkuWithBalance(quantity: 5);

        $this->actingWithInventoryCookie(userId: 10)
            ->withHeaders($this->authHeaders())
            ->post('/api/v1/stock-imports', [
                'file' => $this->csvUpload("sku_code,quantity\nAO-THUN-M,12\n"),
            ])
            ->assertCreated()
            ->assertJsonPath('stock_import.status', 'PREVIEWED')
            ->assertJsonPath('stock_import.has_errors', false)
            ->assertJsonPath('stock_import.created_by', 10)
            ->assertJsonPath('stock_import.lines.0.sku_code', 'AO-THUN-M')
            ->assertJsonPath('stock_import.lines.0.sku_id', $sku->id)
            ->assertJsonPath('stock_import.lines.0.quantity_before', 5)
            ->assertJsonPath('stock_import.lines.0.quantity_after', 12);
    }

    public function test_preview_marks_missing_sku_error_by_row(): void
    {
        $this->actingWithInventoryCookie()
            ->withHeaders($this->authHeaders())
            ->post('/api/v1/stock-imports', [
                'file' => $this->csvUpload("sku_code,quantity\nSKU-KHONG-CO,4\n"),
            ])
            ->assertCreated()
            ->assertJsonPath('stock_import.has_errors', true)
            ->assertJsonPath('stock_import.lines.0.error_message', 'Không tìm thấy SKU.');
    }

    public function test_preview_marks_duplicate_sku_in_same_file(): void
    {
        $this->createSkuWithBalance(quantity: 5);

        $this->actingWithInventoryCookie()
            ->withHeaders($this->authHeaders())
            ->post('/api/v1/stock-imports', [
                'file' => $this->csvUpload("sku_code,quantity\nAO-THUN-M,4\nAO-THUN-M,7\n"),
            ])
            ->assertCreated()
            ->assertJsonPath('stock_import.has_errors', true)
            ->assertJsonPath('stock_import.lines.1.error_message', 'SKU bị lặp trong file.');
    }

    public function test_preview_rejects_duplicate_file_hash(): void
    {
        $this->createSkuWithBalance(quantity: 5);
        $content = "sku_code,quantity\nAO-THUN-M,12\n";

        $this->actingWithInventoryCookie()
            ->withHeaders($this->authHeaders())
            ->post('/api/v1/stock-imports', [
                'file' => $this->csvUpload($content),
            ])
            ->assertCreated();

        $this->actingWithInventoryCookie()
            ->withHeaders($this->authHeaders())
            ->post('/api/v1/stock-imports', [
                'file' => $this->csvUpload($content),
            ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'File này đã được import trước đó.');
    }

    public function test_preview_preserves_raw_invalid_csv_values(): void
    {
        $this->createSkuWithBalance(quantity: 5);

        $this->actingWithInventoryCookie()
            ->withHeaders($this->authHeaders())
            ->post('/api/v1/stock-imports', [
                'file' => $this->csvUpload("sku_code,quantity\n ao-thun-m ,abc\n"),
            ])
            ->assertCreated()
            ->assertJsonPath('stock_import.lines.0.raw_sku_code', ' ao-thun-m ')
            ->assertJsonPath('stock_import.lines.0.raw_quantity', 'abc')
            ->assertJsonPath('stock_import.lines.0.quantity', null)
            ->assertJsonPath('stock_import.lines.0.error_message', 'Số lượng phải là số nguyên.');
    }

    public function test_preview_marks_inactive_sku_error_by_row(): void
    {
        $this->createSkuWithBalance(quantity: 5, active: false);

        $this->actingWithInventoryCookie()
            ->withHeaders($this->authHeaders())
            ->post('/api/v1/stock-imports', [
                'file' => $this->csvUpload("sku_code,quantity\nAO-THUN-M,12\n"),
            ])
            ->assertCreated()
            ->assertJsonPath('stock_import.has_errors', true)
            ->assertJsonPath('stock_import.lines.0.error_message', 'SKU đã ngừng hoạt động.');
    }

    public function test_get_preview_does_not_require_csrf(): void
    {
        $stockImport = StockImport::query()->create([
            'file_name' => 'inventory.csv',
            'file_hash' => str_repeat('b', 64),
            'status' => StockImportStatus::Previewed->value,
            'created_by' => 10,
        ]);

        StockImportLine::query()->create([
            'stock_import_id' => $stockImport->id,
            'row_number' => 2,
            'sku_code' => 'SKU-KHONG-CO',
            'raw_sku_code' => 'SKU-KHONG-CO',
            'raw_quantity' => '4',
            'quantity' => 4,
            'error_message' => 'Không tìm thấy SKU.',
        ]);

        $this->actingWithInventoryCookie()
            ->getJson("/api/v1/stock-imports/{$stockImport->id}/preview")
            ->assertOk()
            ->assertJsonPath('stock_import.lines.0.error_message', 'Không tìm thấy SKU.');
    }

    public function test_staff_can_preview_stock_import(): void
    {
        $this->createSkuWithBalance(quantity: 5);

        $this->actingWithInventoryCookie(role: 'STAFF', userId: 20)
            ->withHeaders($this->authHeaders())
            ->post('/api/v1/stock-imports', [
                'file' => $this->csvUpload("sku_code,quantity\nAO-THUN-M,12\n"),
            ])
            ->assertCreated()
            ->assertJsonPath('stock_import.created_by', 20);
    }

    public function test_confirm_stock_import_synchronizes_balances_and_writes_import_sync_transactions(): void
    {
        $sku = $this->createSkuWithBalance(quantity: 5);
        $stockImport = $this->createPreviewImport($sku, quantity: 12, quantityBefore: 5);

        $this->actingWithInventoryCookie(userId: 10)
            ->withHeaders($this->authHeaders())
            ->postJson("/api/v1/stock-imports/{$stockImport->id}/confirm")
            ->assertOk()
            ->assertJsonPath('stock_import.status', 'CONFIRMED')
            ->assertJsonPath('stock_import.confirmed_at', fn ($value): bool => is_string($value));

        $this->assertDatabaseHas('inventory_balances', [
            'sku_id' => $sku->id,
            'quantity' => 12,
        ]);
        $this->assertDatabaseHas('inventory_transactions', [
            'sku_id' => $sku->id,
            'type' => 'IMPORT_SYNC',
            'quantity_before' => 5,
            'quantity_change' => 7,
            'quantity_after' => 12,
            'reference_type' => 'stock_import',
            'reference_id' => (string) $stockImport->id,
            'reason' => 'Đồng bộ tồn kho từ file CSV.',
            'created_by' => 10,
        ]);
    }

    public function test_confirm_writes_import_sync_transaction_when_quantity_does_not_change(): void
    {
        $sku = $this->createSkuWithBalance(quantity: 5);
        $stockImport = $this->createPreviewImport($sku, quantity: 5, quantityBefore: 5);

        $this->actingWithInventoryCookie()
            ->withHeaders($this->authHeaders())
            ->postJson("/api/v1/stock-imports/{$stockImport->id}/confirm")
            ->assertOk();

        $this->assertDatabaseHas('inventory_transactions', [
            'sku_id' => $sku->id,
            'type' => 'IMPORT_SYNC',
            'quantity_before' => 5,
            'quantity_change' => 0,
            'quantity_after' => 5,
            'reference_type' => 'stock_import',
            'reference_id' => (string) $stockImport->id,
        ]);
    }

    public function test_confirm_does_not_mutate_preview_snapshot(): void
    {
        $sku = $this->createSkuWithBalance(quantity: 5);
        $stockImport = $this->createPreviewImport($sku, quantity: 12, quantityBefore: 3);
        $line = $stockImport->lines()->firstOrFail();

        $this->actingWithInventoryCookie()
            ->withHeaders($this->authHeaders())
            ->postJson("/api/v1/stock-imports/{$stockImport->id}/confirm")
            ->assertOk();

        $line->refresh();
        self::assertSame(3, $line->quantity_before);
        self::assertSame(12, $line->quantity_after);
    }

    public function test_confirm_blocks_import_with_line_errors(): void
    {
        $sku = $this->createSkuWithBalance(quantity: 5);
        $stockImport = $this->createPreviewImport($sku, errorMessage: 'Không tìm thấy SKU.');

        $this->actingWithInventoryCookie()
            ->withHeaders($this->authHeaders())
            ->postJson("/api/v1/stock-imports/{$stockImport->id}/confirm")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Không thể xác nhận file còn dòng lỗi.');

        $this->assertDatabaseHas('inventory_balances', [
            'sku_id' => $sku->id,
            'quantity' => 5,
        ]);
    }

    public function test_confirm_cannot_run_twice(): void
    {
        $sku = $this->createSkuWithBalance(quantity: 5);
        $stockImport = $this->createPreviewImport($sku, quantity: 12, quantityBefore: 5);

        $this->actingWithInventoryCookie()
            ->withHeaders($this->authHeaders())
            ->postJson("/api/v1/stock-imports/{$stockImport->id}/confirm")
            ->assertOk();

        $this->actingWithInventoryCookie()
            ->withHeaders($this->authHeaders())
            ->postJson("/api/v1/stock-imports/{$stockImport->id}/confirm")
            ->assertStatus(409)
            ->assertJsonPath('message', 'File import này đã được xác nhận.');
    }

    public function test_confirm_requires_csrf(): void
    {
        $sku = $this->createSkuWithBalance(quantity: 5);
        $stockImport = $this->createPreviewImport($sku, quantity: 12, quantityBefore: 5);

        $this->actingWithInventoryCookie()
            ->postJson("/api/v1/stock-imports/{$stockImport->id}/confirm")
            ->assertStatus(419);
    }

    public function test_staff_can_confirm_stock_import(): void
    {
        $sku = $this->createSkuWithBalance(quantity: 5);
        $stockImport = $this->createPreviewImport($sku, quantity: 12, quantityBefore: 5);

        $this->actingWithInventoryCookie(role: 'STAFF', userId: 20)
            ->withHeaders($this->authHeaders())
            ->postJson("/api/v1/stock-imports/{$stockImport->id}/confirm")
            ->assertOk()
            ->assertJsonPath('stock_import.status', 'CONFIRMED');

        $this->assertDatabaseHas('inventory_transactions', [
            'sku_id' => $sku->id,
            'type' => 'IMPORT_SYNC',
            'created_by' => 20,
        ]);
    }

    private function csvUpload(string $content, string $name = 'inventory.csv'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'stock-import-');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, 'text/csv', null, true);
    }

    private function createSkuWithBalance(int $quantity, bool $active = true): ProductSku
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
            'active' => $active,
        ]);
        InventoryBalance::query()->create([
            'sku_id' => $sku->id,
            'quantity' => $quantity,
        ]);

        return $sku;
    }

    private function createPreviewImport(
        ProductSku $sku,
        int $quantity = 12,
        int $quantityBefore = 5,
        ?string $errorMessage = null,
    ): StockImport {
        $stockImport = StockImport::query()->create([
            'file_name' => 'inventory.csv',
            'file_hash' => str_repeat('c', 64),
            'status' => StockImportStatus::Previewed->value,
            'created_by' => 10,
        ]);
        StockImportLine::query()->create([
            'stock_import_id' => $stockImport->id,
            'row_number' => 2,
            'sku_code' => $sku->sku_code,
            'raw_sku_code' => $sku->sku_code,
            'raw_quantity' => (string) $quantity,
            'sku_id' => $sku->id,
            'quantity' => $quantity,
            'quantity_before' => $quantityBefore,
            'quantity_after' => $quantity,
            'error_message' => $errorMessage,
        ]);

        return $stockImport;
    }
}
