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
            'raw_product_name',
            'raw_variant',
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
                'raw_product_name' => null,
                'raw_variant' => null,
                'raw_sku_code' => ' ao-thun-m ',
                'raw_quantity_sold' => '2',
                'sku_code' => 'AO-THUN-M',
                'quantity_sold' => 2,
                'error_message' => null,
            ],
        ], $rows);
    }

    public function test_daily_sale_csv_parser_accepts_product_name_variant_template(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'daily-sale-');
        file_put_contents(
            $path,
            "TÊN,MÀU / SIZE,Số Lượng Bán\n".
            "Áo Thun DirtyCoins Patch In Heart,Black / M,x 1\n"
        );

        $rows = app(\App\Infrastructure\CsvDailySaleParser::class)->parse($path);

        self::assertSame([
            [
                'row_number' => 2,
                'raw_product_name' => 'Áo Thun DirtyCoins Patch In Heart',
                'raw_variant' => 'Black / M',
                'raw_sku_code' => '',
                'raw_quantity_sold' => 'x 1',
                'sku_code' => '',
                'quantity_sold' => 1,
                'error_message' => null,
            ],
        ], $rows);
    }

    public function test_daily_sale_csv_parser_rejects_wrong_header(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'daily-sale-');
        file_put_contents($path, "sku_code,quantity\nAO-THUN-M,2\n");

        $this->expectException(\App\Domain\Exceptions\InventoryBusinessException::class);
        $this->expectExceptionMessage('File CSV phải có cột sku_code + quantity_sold hoặc TÊN + MÀU / SIZE + Số Lượng Bán.');

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

    public function test_create_daily_sale_matches_product_name_and_variant_then_confirm_decrements_stock(): void
    {
        $sku = $this->createSkuWithBalance(
            quantity: 10,
            skuCode: 'DC-HEART-BLACK-M',
            productName: 'Áo Thun DirtyCoins Patch In Heart',
            size: 'Black / M',
        );

        $this->actingWithInventoryCookie(userId: 10)
            ->withHeaders($this->authHeaders())
            ->post('/api/v1/daily-sales', [
                'sales_date' => now()->toDateString(),
                'file' => $this->csvUpload(
                    "TÊN,MÀU / SIZE,Số Lượng Bán\n".
                    "Áo Thun DirtyCoins Patch In Heart,Black / M,x 1\n"
                ),
            ])
            ->assertCreated()
            ->assertJsonPath('daily_sale.has_errors', false)
            ->assertJsonPath('daily_sale.lines.0.raw_product_name', 'Áo Thun DirtyCoins Patch In Heart')
            ->assertJsonPath('daily_sale.lines.0.raw_variant', 'Black / M')
            ->assertJsonPath('daily_sale.lines.0.sku_code', 'DC-HEART-BLACK-M')
            ->assertJsonPath('daily_sale.lines.0.sku_id', $sku->id)
            ->assertJsonPath('daily_sale.lines.0.quantity_sold', 1)
            ->assertJsonPath('daily_sale.lines.0.preview_quantity_after', 9);

        $dailySale = DailySale::query()->latest('id')->firstOrFail();

        $this->actingWithInventoryCookie(userId: 10)
            ->withHeaders($this->authHeaders())
            ->postJson("/api/v1/daily-sales/{$dailySale->id}/confirm")
            ->assertOk()
            ->assertJsonPath('daily_sale.status', 'CONFIRMED');

        $this->assertDatabaseHas('inventory_balances', [
            'sku_id' => $sku->id,
            'quantity' => 9,
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

    private function createSkuWithBalance(
        int $quantity,
        bool $active = true,
        string $skuCode = 'AO-THUN-M',
        string $productName = 'Áo thun',
        string $size = 'M',
    ): ProductSku
    {
        $product = Product::query()->firstOrCreate(
            ['product_code' => str_contains($skuCode, '-') ? substr($skuCode, 0, (int) strrpos($skuCode, '-')) : $skuCode],
            ['name' => $productName, 'active' => true],
        );
        $sku = ProductSku::query()->firstOrCreate(
            ['sku_code' => $skuCode],
            ['product_id' => $product->id, 'size' => $size, 'active' => $active],
        );
        // Nếu balance chưa tồn tại thì tạo, nếu có rồi thì cập nhật số lượng
        InventoryBalance::query()->updateOrCreate(
            ['sku_id' => $sku->id],
            ['quantity' => $quantity],
        );

        return $sku;
    }

    private function createDraftDailySale(string $salesDate = '2026-08-01', ?ProductSku $sku = null): DailySale
    {
        if (! $sku) {
            $sku = $this->createSkuWithBalance(quantity: 10);
        }
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

    // =========================================================================
    // Task 3: Confirm Daily Sale Tests
    // =========================================================================

    public function test_confirm_daily_sale_decrements_stock_and_returns_confirmed(): void
    {
        $sku = $this->createSkuWithBalance(quantity: 10);
        $dailySale = $this->createDraftDailySale(); // line qty_sold=2

        $this->actingWithInventoryCookie(userId: 10)
            ->withHeaders($this->authHeaders())
            ->postJson("/api/v1/daily-sales/{$dailySale->id}/confirm")
            ->assertOk()
            ->assertJsonPath('daily_sale.status', 'CONFIRMED')
            ->assertJsonPath('daily_sale.confirmed_by', 10);

        $this->assertDatabaseHas('inventory_balances', [
            'sku_id' => $sku->id,
            'quantity' => 8,
        ]);
        $this->assertDatabaseHas('inventory_transactions', [
            'sku_id'         => $sku->id,
            'type'           => 'SALE',
            'quantity_change' => -2,
            'reference_type' => 'daily_sale',
        ]);
    }

    public function test_confirm_daily_sale_rollback_if_insufficient_stock(): void
    {
        $sku = $this->createSkuWithBalance(quantity: 1); // tồn = 1 nhưng bán 2
        $dailySale = $this->createDraftDailySale('2026-08-01', $sku);      // line qty_sold=2

        $this->actingWithInventoryCookie()
            ->withHeaders($this->authHeaders())
            ->postJson("/api/v1/daily-sales/{$dailySale->id}/confirm")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Không đủ tồn kho để xác nhận phiếu bán.');

        $this->assertDatabaseHas('inventory_balances', [
            'sku_id'   => $sku->id,
            'quantity' => 1,
        ]);
        $this->assertDatabaseHas('daily_sales', [
            'id'     => $dailySale->id,
            'status' => 'DRAFT',
        ]);
    }

    public function test_confirm_daily_sale_blocks_double_confirm(): void
    {
        $this->createSkuWithBalance(quantity: 10);
        $dailySale = $this->createDraftDailySale();

        $this->actingWithInventoryCookie()
            ->withHeaders($this->authHeaders())
            ->postJson("/api/v1/daily-sales/{$dailySale->id}/confirm")
            ->assertOk();

        $this->actingWithInventoryCookie()
            ->withHeaders($this->authHeaders())
            ->postJson("/api/v1/daily-sales/{$dailySale->id}/confirm")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Phiếu này đã được xác nhận.');
    }

    public function test_confirm_daily_sale_blocks_cancelled_sale(): void
    {
        $dailySale = DailySale::query()->create([
            'sales_date'  => '2026-08-01',
            'file_name'   => 'x.csv',
            'file_hash'   => str_repeat('e', 64),
            'status'      => DailySaleStatus::Cancelled->value,
            'cancelled_at' => now(),
        ]);

        $this->actingWithInventoryCookie()
            ->withHeaders($this->authHeaders())
            ->postJson("/api/v1/daily-sales/{$dailySale->id}/confirm")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Phiếu đã hủy, không thể xác nhận.');
    }

    public function test_confirm_daily_sale_blocks_when_lines_have_errors(): void
    {
        $dailySale = DailySale::query()->create([
            'sales_date' => '2026-08-01',
            'file_name'  => 'err.csv',
            'file_hash'  => str_repeat('f', 64),
            'status'     => DailySaleStatus::Draft->value,
        ]);
        DailySaleLine::query()->create([
            'daily_sale_id'    => $dailySale->id,
            'row_number'       => 2,
            'sku_code'         => 'UNKNOWN',
            'raw_sku_code'     => 'UNKNOWN',
            'raw_quantity_sold' => '2',
            'error_message'    => 'Không tìm thấy SKU.',
        ]);

        $this->actingWithInventoryCookie()
            ->withHeaders($this->authHeaders())
            ->postJson("/api/v1/daily-sales/{$dailySale->id}/confirm")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Không thể xác nhận phiếu còn dòng lỗi.');
    }

    public function test_confirm_daily_sale_blocks_when_sku_is_deactivated_after_draft(): void
    {
        $sku = $this->createSkuWithBalance(quantity: 10);
        $dailySale = $this->createDraftDailySale(sku: $sku);

        $sku->active = false;
        $sku->save();

        $this->actingWithInventoryCookie()
            ->withHeaders($this->authHeaders())
            ->postJson("/api/v1/daily-sales/{$dailySale->id}/confirm")
            ->assertStatus(409)
            ->assertJsonPath('message', "SKU {$sku->sku_code} đã ngừng hoạt động và không thể xác nhận.");

        $this->assertDatabaseHas('inventory_balances', [
            'sku_id' => $sku->id,
            'quantity' => 10,
        ]);
        $this->assertDatabaseHas('daily_sales', [
            'id' => $dailySale->id,
            'status' => 'DRAFT',
        ]);
    }

    public function test_confirm_daily_sale_blocks_same_date_already_confirmed(): void
    {
        // Đã có phiếu CONFIRMED cùng ngày
        DailySale::query()->create([
            'sales_date'          => '2026-08-01',
            'confirmed_sales_date' => '2026-08-01',
            'file_name'           => 'prev.csv',
            'file_hash'           => str_repeat('g', 64),
            'status'              => DailySaleStatus::Confirmed->value,
            'confirmed_at'        => now(),
        ]);

        $this->createSkuWithBalance(quantity: 10);
        $draft = $this->createDraftDailySale(salesDate: '2026-08-01');

        $this->actingWithInventoryCookie()
            ->withHeaders($this->authHeaders())
            ->postJson("/api/v1/daily-sales/{$draft->id}/confirm")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Ngày bán này đã có phiếu được xác nhận.');
    }

    public function test_confirm_daily_sale_requires_csrf(): void
    {
        $dailySale = $this->createDraftDailySale();

        $this->actingWithInventoryCookie()
            ->post("/api/v1/daily-sales/{$dailySale->id}/confirm")
            ->assertStatus(419);
    }

    public function test_staff_can_confirm_daily_sale(): void
    {
        $sku = $this->createSkuWithBalance(quantity: 10);
        $dailySale = $this->createDraftDailySale();

        $this->actingWithInventoryCookie(role: 'STAFF', userId: 30)
            ->withHeaders($this->authHeaders())
            ->postJson("/api/v1/daily-sales/{$dailySale->id}/confirm")
            ->assertOk()
            ->assertJsonPath('daily_sale.confirmed_by', 30);

        $this->assertDatabaseHas('inventory_balances', [
            'sku_id'   => $sku->id,
            'quantity' => 8,
        ]);
    }

    // =========================================================================
    // Task 4: Cancel Daily Sale Tests
    // =========================================================================

    public function test_cancel_daily_sale_reverses_balances_and_writes_sale_reversal_transactions(): void
    {
        $sku = $this->createSkuWithBalance(quantity: 10);
        $dailySale = $this->createDraftDailySale(); // line qty_sold = 2

        // Confirm đầu tiên
        $this->actingWithInventoryCookie(userId: 10)
            ->withHeaders($this->authHeaders())
            ->postJson("/api/v1/daily-sales/{$dailySale->id}/confirm")
            ->assertOk();

        // Hủy phiếu
        $this->actingWithInventoryCookie(userId: 10)
            ->withHeaders($this->authHeaders())
            ->postJson("/api/v1/daily-sales/{$dailySale->id}/cancel", [
                'reason' => 'Khách trả đơn lỗi nhập.',
            ])
            ->assertOk()
            ->assertJsonPath('daily_sale.status', 'CANCELLED')
            ->assertJsonPath('daily_sale.cancelled_by', 10)
            ->assertJsonPath('daily_sale.cancel_reason', 'Khách trả đơn lỗi nhập.')
            ->assertJsonPath('daily_sale.confirmed_sales_date', null);

        // Kiểm tra tồn kho được cộng trả lại (8 + 2 = 10)
        $this->assertDatabaseHas('inventory_balances', [
            'sku_id'   => $sku->id,
            'quantity' => 10,
        ]);

        // Kiểm tra audit transaction
        $this->assertDatabaseHas('inventory_transactions', [
            'sku_id'          => $sku->id,
            'type'            => 'SALE_REVERSAL',
            'quantity_change' => 2,
            'reference_type'  => 'daily_sale',
            'reason'          => 'Khách trả đơn lỗi nhập.',
        ]);
    }

    public function test_cancel_daily_sale_blocks_when_sku_is_deactivated_after_confirm(): void
    {
        $sku = $this->createSkuWithBalance(quantity: 10);
        $dailySale = $this->createDraftDailySale();

        // Confirm
        $this->actingWithInventoryCookie()
            ->withHeaders($this->authHeaders())
            ->postJson("/api/v1/daily-sales/{$dailySale->id}/confirm")
            ->assertOk();

        // Deactivate SKU
        $sku->active = false;
        $sku->save();

        // Hủy phiếu -> chặn 409
        $this->actingWithInventoryCookie()
            ->withHeaders($this->authHeaders())
            ->postJson("/api/v1/daily-sales/{$dailySale->id}/cancel", [
                'reason' => 'Hủy đơn',
            ])
            ->assertStatus(409)
            ->assertJsonPath('message', "SKU {$sku->sku_code} đã ngừng hoạt động và không thể hủy.");
    }

    public function test_cancel_daily_sale_requires_confirmed_status(): void
    {
        $dailySale = $this->createDraftDailySale(); // Đang ở DRAFT

        $this->actingWithInventoryCookie()
            ->withHeaders($this->authHeaders())
            ->postJson("/api/v1/daily-sales/{$dailySale->id}/cancel", [
                'reason' => 'Hủy đơn',
            ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Phiếu bán chưa xác nhận nên không thể hủy.');
    }

    public function test_cancel_daily_sale_cannot_run_twice(): void
    {
        $dailySale = $this->createDraftDailySale();

        // Confirm
        $this->actingWithInventoryCookie()
            ->withHeaders($this->authHeaders())
            ->postJson("/api/v1/daily-sales/{$dailySale->id}/confirm")
            ->assertOk();

        // Hủy lần 1
        $this->actingWithInventoryCookie()
            ->withHeaders($this->authHeaders())
            ->postJson("/api/v1/daily-sales/{$dailySale->id}/cancel", [
                'reason' => 'Hủy lần 1',
            ])
            ->assertOk();

        // Hủy lần 2 -> chặn 409
        $this->actingWithInventoryCookie()
            ->withHeaders($this->authHeaders())
            ->postJson("/api/v1/daily-sales/{$dailySale->id}/cancel", [
                'reason' => 'Hủy lần 2',
            ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Phiếu bán này đã bị hủy.');
    }

    public function test_cancel_daily_sale_requires_reason(): void
    {
        $dailySale = $this->createDraftDailySale();

        $this->actingWithInventoryCookie()
            ->withHeaders($this->authHeaders())
            ->postJson("/api/v1/daily-sales/{$dailySale->id}/confirm")
            ->assertOk();

        $this->actingWithInventoryCookie()
            ->withHeaders($this->authHeaders())
            ->postJson("/api/v1/daily-sales/{$dailySale->id}/cancel", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_cancel_daily_sale_requires_csrf(): void
    {
        $dailySale = $this->createDraftDailySale();

        $this->actingWithInventoryCookie()
            ->post("/api/v1/daily-sales/{$dailySale->id}/cancel", [
                'reason' => 'Hủy nháp',
            ])
            ->assertStatus(419);
    }

    public function test_staff_can_cancel_daily_sale(): void
    {
        $sku = $this->createSkuWithBalance(quantity: 10);
        $dailySale = $this->createDraftDailySale();

        $this->actingWithInventoryCookie()
            ->withHeaders($this->authHeaders())
            ->postJson("/api/v1/daily-sales/{$dailySale->id}/confirm")
            ->assertOk();

        $this->actingWithInventoryCookie(role: 'STAFF', userId: 40)
            ->withHeaders($this->authHeaders())
            ->postJson("/api/v1/daily-sales/{$dailySale->id}/cancel", [
                'reason' => 'Staff hủy',
            ])
            ->assertOk()
            ->assertJsonPath('daily_sale.status', 'CANCELLED')
            ->assertJsonPath('daily_sale.cancelled_by', 40);

        $this->assertDatabaseHas('inventory_balances', [
            'sku_id'   => $sku->id,
            'quantity' => 10,
        ]);
    }
}
