<?php

namespace Tests\Feature;

use App\Domain\Enums\DailySaleStatus;
use App\Models\DailySale;
use App\Models\DailySaleLine;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\ProductSku;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;

final class DailySaleApiTest extends InventoryFeatureTestCase
{
    public function test_daily_sales_tables_exist(): void
    {
        self::assertTrue(Schema::hasTable('daily_sales'));
        self::assertTrue(Schema::hasTable('daily_sale_lines'));
        self::assertTrue(Schema::hasColumns('daily_sales', [
            'sales_date',
            'confirmed_sales_date',
            'file_name',
            'file_hash',
            'status',
            'confirmed_at',
            'cancelled_at',
            'cancel_reason',
            'created_by',
            'confirmed_by',
            'cancelled_by',
        ]));
        self::assertTrue(Schema::hasColumns('daily_sale_lines', [
            'daily_sale_id',
            'row_number',
            'sku_code',
            'sku_id',
            'raw_sku_code',
            'raw_quantity_sold',
            'quantity_sold',
            'preview_quantity_before',
            'preview_quantity_after',
            'error_message',
        ]));
    }

    public function test_daily_sale_model_relationships_work(): void
    {
        $sku = $this->createSkuWithBalance(quantity: 10);
        $dailySale = DailySale::query()->create([
            'sales_date' => '2026-08-03',
            'file_name' => 'daily-sales.csv',
            'file_hash' => str_repeat('a', 64),
            'status' => DailySaleStatus::Draft->value,
            'created_by' => 10,
        ]);
        DailySaleLine::query()->create([
            'daily_sale_id' => $dailySale->id,
            'row_number' => 2,
            'sku_code' => $sku->sku_code,
            'raw_sku_code' => $sku->sku_code,
            'raw_quantity_sold' => '2',
            'sku_id' => $sku->id,
            'quantity_sold' => 2,
            'preview_quantity_before' => 10,
            'preview_quantity_after' => 8,
        ]);

        self::assertSame('AO-THUN-M', $dailySale->lines()->first()->sku->sku_code);
        self::assertSame(DailySaleStatus::Draft, $dailySale->status);
    }

    public function test_daily_sale_csv_parser_accepts_template_and_normalizes_sku_code(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'daily-sale-');
        file_put_contents($path, "sku_code,quantity_sold\n ao-thun-m ,2\n");

        $rows = app(\App\Infrastructure\CsvDailySaleParser::class)->parse($path);

        self::assertSame([
            [
                'row_number' => 2,
                'raw_sku_code' => ' ao-thun-m ',
                'raw_quantity_sold' => '2',
                'sku_code' => 'AO-THUN-M',
                'quantity_sold' => 2,
                'error_message' => null,
            ],
        ], $rows);
    }

    public function test_daily_sale_csv_parser_rejects_wrong_header(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'daily-sale-');
        file_put_contents($path, "sku_code,quantity\nAO-THUN-M,2\n");

        $this->expectException(\App\Domain\Exceptions\InventoryBusinessException::class);
        $this->expectExceptionMessage('File CSV phải có đúng hai cột sku_code và quantity_sold.');

        app(\App\Infrastructure\CsvDailySaleParser::class)->parse($path);
    }

    public function test_daily_sale_csv_parser_marks_invalid_quantity_rows(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'daily-sale-');
        file_put_contents($path, "sku_code,quantity_sold\nAO-THUN-M,0\nAO-THUN-L,abc\n");

        $rows = app(\App\Infrastructure\CsvDailySaleParser::class)->parse($path);

        self::assertSame('Số lượng bán phải lớn hơn 0.', $rows[0]['error_message']);
        self::assertSame('0', $rows[0]['raw_quantity_sold']);
        self::assertSame('Số lượng bán phải là số nguyên.', $rows[1]['error_message']);
        self::assertSame('abc', $rows[1]['raw_quantity_sold']);
    }

    public function test_daily_sale_csv_parser_handles_bom_blank_rows_and_normalized_sku(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'daily-sale-');
        file_put_contents($path, "\xEF\xBB\xBFsku_code,quantity_sold\n\n ao-thun-m ,2\nAO-THUN-M,3\n");

        $rows = app(\App\Infrastructure\CsvDailySaleParser::class)->parse($path);

        self::assertCount(2, $rows);
        self::assertSame('AO-THUN-M', $rows[0]['sku_code']);
        self::assertSame('AO-THUN-M', $rows[1]['sku_code']);
    }

    public function test_daily_sale_csv_parser_rejects_file_without_data_rows(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'daily-sale-');
        file_put_contents($path, "sku_code,quantity_sold\n\n");

        $this->expectException(\App\Domain\Exceptions\InventoryBusinessException::class);
        $this->expectExceptionMessage('File CSV phải có ít nhất một dòng dữ liệu.');

        app(\App\Infrastructure\CsvDailySaleParser::class)->parse($path);
    }

    public function test_daily_sale_csv_parser_rejects_more_than_5000_data_rows(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'daily-sale-');
        file_put_contents($path, "sku_code,quantity_sold\n".str_repeat("AO-THUN-M,1\n", 5001));

        $this->expectException(\App\Domain\Exceptions\InventoryBusinessException::class);
        $this->expectExceptionMessage('File CSV chỉ được có tối đa 5000 dòng dữ liệu.');

        app(\App\Infrastructure\CsvDailySaleParser::class)->parse($path);
    }

    public function test_create_daily_sale_draft_creates_sale_and_grouped_lines(): void
    {
        $sku = $this->createSkuWithBalance(quantity: 10);

        $this->actingWithInventoryCookie(userId: 10)
            ->withHeaders($this->authHeaders())
            ->post('/api/v1/daily-sales', [
                'sales_date' => now()->toDateString(),
                'file' => $this->csvUpload("sku_code,quantity_sold\nAO-THUN-M,2\n ao-thun-m ,3\n"),
            ])
            ->assertCreated()
            ->assertJsonPath('daily_sale.status', 'DRAFT')
            ->assertJsonPath('daily_sale.created_by', 10)
            ->assertJsonPath('daily_sale.file_hash', fn ($value): bool => is_string($value) && strlen($value) === 64)
            ->assertJsonPath('daily_sale.lines.0.sku_id', $sku->id)
            ->assertJsonPath('daily_sale.lines.0.quantity_sold', 5)
            ->assertJsonPath('daily_sale.lines.0.preview_quantity_before', 10)
            ->assertJsonPath('daily_sale.lines.0.preview_quantity_after', 5);

        $this->assertDatabaseHas('inventory_balances', [
            'sku_id' => $sku->id,
            'quantity' => 10,
        ]);
    }

    public function test_create_daily_sale_marks_missing_sku_error_by_line(): void
    {
        $this->actingWithInventoryCookie()
            ->withHeaders($this->authHeaders())
            ->post('/api/v1/daily-sales', [
                'sales_date' => now()->toDateString(),
                'file' => $this->csvUpload("sku_code,quantity_sold\nSKU-KHONG-CO,2\n"),
            ])
            ->assertCreated()
            ->assertJsonPath('daily_sale.has_errors', true)
            ->assertJsonPath('daily_sale.lines.0.error_message', 'Không tìm thấy SKU.');
    }

    public function test_create_daily_sale_marks_inactive_sku_error_by_line(): void
    {
        $this->createSkuWithBalance(quantity: 10, active: false);

        $this->actingWithInventoryCookie()
            ->withHeaders($this->authHeaders())
            ->post('/api/v1/daily-sales', [
                'sales_date' => now()->toDateString(),
                'file' => $this->csvUpload("sku_code,quantity_sold\nAO-THUN-M,2\n"),
            ])
            ->assertCreated()
            ->assertJsonPath('daily_sale.has_errors', true)
            ->assertJsonPath('daily_sale.lines.0.error_message', 'SKU đã ngừng hoạt động.');
    }

    public function test_create_daily_sale_blocks_when_confirmed_sale_exists_for_same_date(): void
    {
        DailySale::query()->create([
            'sales_date' => now()->toDateString(),
            'confirmed_sales_date' => now()->toDateString(),
            'file_name' => 'old.csv',
            'file_hash' => str_repeat('b', 64),
            'status' => DailySaleStatus::Confirmed->value,
        ]);

        $this->actingWithInventoryCookie()
            ->withHeaders($this->authHeaders())
            ->post('/api/v1/daily-sales', [
                'sales_date' => now()->toDateString(),
                'file' => $this->csvUpload("sku_code,quantity_sold\nAO-THUN-M,2\n"),
            ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Ngày bán này đã có phiếu được xác nhận.');
    }

    public function test_create_daily_sale_rejects_future_sales_date(): void
    {
        $this->actingWithInventoryCookie()
            ->withHeaders($this->authHeaders())
            ->post('/api/v1/daily-sales', [
                'sales_date' => now()->addDay()->toDateString(),
                'file' => $this->csvUpload("sku_code,quantity_sold\nAO-THUN-M,2\n"),
            ])
            ->assertStatus(422);
    }

    public function test_get_daily_sale_does_not_require_csrf(): void
    {
        $dailySale = $this->createDraftDailySale();

        $this->actingWithInventoryCookie()
            ->getJson("/api/v1/daily-sales/{$dailySale->id}")
            ->assertOk()
            ->assertJsonPath('daily_sale.id', $dailySale->id)
            ->assertJsonPath('daily_sale.lines.0.quantity_sold', 2);
    }

    public function test_list_daily_sales_paginates_and_does_not_return_lines(): void
    {
        $this->createDraftDailySale();

        $this->actingWithInventoryCookie()
            ->getJson('/api/v1/daily-sales')
            ->assertOk()
            ->assertJsonPath('daily_sales.0.lines_count', 1)
            ->assertJsonMissingPath('daily_sales.0.lines')
            ->assertJsonPath('meta.per_page', 20);
    }

    public function test_list_daily_sales_filters_by_date_range_and_status(): void
    {
        $this->createDraftDailySale(salesDate: '2026-08-01');
        DailySale::query()->create([
            'sales_date' => '2026-08-02',
            'file_name' => 'confirmed.csv',
            'file_hash' => str_repeat('c', 64),
            'status' => DailySaleStatus::Confirmed->value,
            'confirmed_sales_date' => '2026-08-02',
        ]);

        $this->actingWithInventoryCookie()
            ->getJson('/api/v1/daily-sales?date_from=2026-08-01&date_to=2026-08-01&status=DRAFT')
            ->assertOk()
            ->assertJsonCount(1, 'daily_sales')
            ->assertJsonPath('daily_sales.0.sales_date', '2026-08-01');
    }

    public function test_list_daily_sales_rejects_date_to_before_date_from(): void
    {
        $this->actingWithInventoryCookie()
            ->getJson('/api/v1/daily-sales?date_from=2026-08-02&date_to=2026-08-01')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Khoảng ngày lọc không hợp lệ.');
    }

    public function test_staff_can_create_daily_sale_draft(): void
    {
        $this->createSkuWithBalance(quantity: 10);

        $this->actingWithInventoryCookie(role: 'STAFF', userId: 20)
            ->withHeaders($this->authHeaders())
            ->post('/api/v1/daily-sales', [
                'sales_date' => now()->toDateString(),
                'file' => $this->csvUpload("sku_code,quantity_sold\nAO-THUN-M,2\n"),
            ])
            ->assertCreated()
            ->assertJsonPath('daily_sale.created_by', 20);
    }

    public function test_create_daily_sale_requires_csrf(): void
    {
        $this->actingWithInventoryCookie()
            ->post('/api/v1/daily-sales', [
                'sales_date' => now()->toDateString(),
                'file' => $this->csvUpload("sku_code,quantity_sold\nAO-THUN-M,2\n"),
            ])
            ->assertStatus(419);
    }

    private function csvUpload(string $content, string $name = 'daily-sales.csv'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'daily-sale-');
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

    private function createDraftDailySale(string $salesDate = '2026-08-01'): DailySale
    {
        $sku = $this->createSkuWithBalance(quantity: 10);
        $dailySale = DailySale::query()->create([
            'sales_date' => $salesDate,
            'file_name' => 'daily-sales.csv',
            'file_hash' => str_repeat('d', 64),
            'status' => DailySaleStatus::Draft->value,
            'created_by' => 10,
        ]);
        DailySaleLine::query()->create([
            'daily_sale_id' => $dailySale->id,
            'row_number' => 2,
            'sku_code' => $sku->sku_code,
            'raw_sku_code' => $sku->sku_code,
            'raw_quantity_sold' => '2',
            'sku_id' => $sku->id,
            'quantity_sold' => 2,
            'preview_quantity_before' => 10,
            'preview_quantity_after' => 8,
        ]);

        return $dailySale;
    }
}
