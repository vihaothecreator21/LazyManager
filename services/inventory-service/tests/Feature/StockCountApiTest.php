<?php

namespace Tests\Feature;

use App\Domain\Enums\StockCountStatus;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\ProductSku;
use App\Models\StockCount;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class StockCountApiTest extends InventoryFeatureTestCase
{
    public function test_stock_count_tables_exist(): void
    {
        self::assertTrue(Schema::hasTable('stock_counts'));
        self::assertTrue(Schema::hasColumns('stock_counts', ['name', 'count_date', 'status', 'created_by']));
        self::assertTrue(Schema::hasTable('stock_count_lines'));
        self::assertTrue(Schema::hasColumns('stock_count_lines', [
            'stock_count_id',
            'sku_id',
            'sku_code',
            'product_id',
            'product_code',
            'product_name',
            'size',
            'expected_quantity',
            'actual_quantity',
            'variance',
            'note',
        ]));
    }

    public function test_stock_count_model_relationships_work(): void
    {
        $sku = $this->createSkuWithBalance(quantity: 10);
        $stockCount = $this->createStockCount(name: 'Kiểm kho sáng thứ Hai');

        $line = $stockCount->lines()->create($this->lineAttributes($sku));

        self::assertSame(1, $stockCount->lines()->count());
        self::assertSame('AO-THUN-M', $line->sku_code);
        self::assertSame(StockCountStatus::Draft, $stockCount->status);
    }

    public function test_stock_count_schema_matches_plan_constraints(): void
    {
        self::assertTrue($this->columnIsNullable('stock_counts', 'name'));
        self::assertFalse($this->columnIsNullable('stock_count_lines', 'sku_id'));
        self::assertFalse($this->columnIsNullable('stock_count_lines', 'product_id'));
        self::assertFalse($this->columnIsNullable('stock_count_lines', 'size'));
        self::assertSame(255, $this->columnLength('stock_count_lines', 'sku_code'));
        self::assertSame(255, $this->columnLength('stock_count_lines', 'product_code'));
        self::assertSame(100, $this->columnLength('stock_count_lines', 'size'));
        self::assertTrue($this->indexExists('stock_counts', 'stock_counts_created_at_index'));
    }

    public function test_stock_count_supports_counted_status_and_optional_name(): void
    {
        $stockCount = $this->createStockCount(status: StockCountStatus::Counted);

        self::assertNull($stockCount->name);
        self::assertSame(StockCountStatus::Counted, $stockCount->status);
    }

    public function test_stock_count_lines_allow_each_sku_once_per_count(): void
    {
        $sku = $this->createSkuWithBalance(quantity: 10);
        $stockCount = $this->createStockCount();

        $stockCount->lines()->create($this->lineAttributes($sku));

        $this->expectException(QueryException::class);

        $stockCount->lines()->create($this->lineAttributes($sku));
    }

    public function test_stock_count_line_expected_quantity_cannot_be_negative(): void
    {
        $sku = $this->createSkuWithBalance(quantity: 10);
        $stockCount = $this->createStockCount();

        $this->expectException(QueryException::class);

        $stockCount->lines()->create(array_merge(
            $this->lineAttributes($sku),
            ['expected_quantity' => -1],
        ));
    }

    public function test_stock_count_line_actual_quantity_cannot_be_negative(): void
    {
        $sku = $this->createSkuWithBalance(quantity: 10);
        $stockCount = $this->createStockCount();

        $stockCount->lines()->create($this->lineAttributes($sku));

        $this->expectException(QueryException::class);

        $stockCount->lines()->update(['actual_quantity' => -1]);
    }

    public function test_authenticated_user_can_create_stock_count_and_gets_201(): void
    {
        $this->createSkuWithBalance(quantity: 10);

        $response = $this->actingWithInventoryCookie()
            ->postJson('/api/v1/stock-counts', [
                'name' => 'Kiểm kho sáng thứ Hai',
                'count_date' => '2026-08-08',
            ], $this->authHeaders());

        $response->assertStatus(201);
        $response->assertJsonPath('stock_count.status', 'DRAFT');
        $response->assertJsonPath('stock_count.name', 'Kiểm kho sáng thứ Hai');

        $this->assertDatabaseHas('stock_count_lines', [
            'sku_id' => ProductSku::query()->where('sku_code', 'AO-THUN-M')->value('id'),
            'sku_code' => 'AO-THUN-M',
            'product_name' => 'Áo thun',
            'size' => 'M',
            'expected_quantity' => 10,
            'actual_quantity' => null,
            'variance' => null,
        ]);
    }

    public function test_expected_quantity_stays_unchanged_after_balance_update(): void
    {
        $sku = $this->createSkuWithBalance(quantity: 10);

        $response = $this->actingWithInventoryCookie()
            ->postJson('/api/v1/stock-counts', [], $this->authHeaders());

        $response->assertStatus(201);

        InventoryBalance::query()->where('sku_id', $sku->id)->update(['quantity' => 99]);

        $this->assertDatabaseHas('stock_count_lines', [
            'sku_id' => $sku->id,
            'expected_quantity' => 10,
        ]);
    }

    public function test_inactive_sku_is_skipped_when_creating_stock_count(): void
    {
        $this->createSkuWithBalance(quantity: 10);

        $inactiveProduct = Product::query()->firstOrCreate(
            ['product_code' => 'QUAN-JEAN'],
            ['name' => 'Quần jean', 'active' => true],
        );
        $inactiveSku = ProductSku::query()->firstOrCreate(
            ['sku_code' => 'QUAN-JEAN-30'],
            ['product_id' => $inactiveProduct->id, 'size' => '30', 'active' => false],
        );
        InventoryBalance::query()->updateOrCreate(
            ['sku_id' => $inactiveSku->id],
            ['quantity' => 5],
        );

        $response = $this->actingWithInventoryCookie()
            ->postJson('/api/v1/stock-counts', [], $this->authHeaders());

        $response->assertStatus(201);
        $this->assertDatabaseMissing('stock_count_lines', ['sku_id' => $inactiveSku->id]);
        $this->assertDatabaseHas('stock_count_lines', ['sku_code' => 'AO-THUN-M']);
    }

    public function test_inactive_product_is_skipped_when_creating_stock_count(): void
    {
        $this->createSkuWithBalance(quantity: 10);

        $inactiveProduct = Product::query()->create([
            'product_code' => 'VAY-HOA',
            'name' => 'Váy hoa',
            'active' => false,
        ]);
        $inactiveProductSku = ProductSku::query()->create([
            'sku_code' => 'VAY-HOA-S',
            'product_id' => $inactiveProduct->id,
            'size' => 'S',
            'active' => true,
        ]);
        InventoryBalance::query()->create(['sku_id' => $inactiveProductSku->id, 'quantity' => 4]);

        $response = $this->actingWithInventoryCookie()
            ->postJson('/api/v1/stock-counts', [], $this->authHeaders());

        $response->assertStatus(201);
        $this->assertDatabaseMissing('stock_count_lines', ['sku_id' => $inactiveProductSku->id]);
        $this->assertDatabaseHas('stock_count_lines', ['sku_code' => 'AO-THUN-M']);
    }

    public function test_soft_deleted_sku_is_skipped_when_creating_stock_count(): void
    {
        $this->createSkuWithBalance(quantity: 10);

        $product2 = Product::query()->firstOrCreate(
            ['product_code' => 'MU-LUOI-TRAI'],
            ['name' => 'Mũ lưỡi trai', 'active' => true],
        );
        $deletedSku = ProductSku::query()->create([
            'sku_code' => 'MU-LUOI-TRAI-M',
            'product_id' => $product2->id,
            'size' => 'M',
            'active' => true,
        ]);
        InventoryBalance::query()->create(['sku_id' => $deletedSku->id, 'quantity' => 3]);
        $deletedSku->delete();

        $response = $this->actingWithInventoryCookie()
            ->postJson('/api/v1/stock-counts', [], $this->authHeaders());

        $response->assertStatus(201);
        $this->assertDatabaseMissing('stock_count_lines', ['sku_id' => $deletedSku->id]);
    }

    public function test_soft_deleted_product_is_skipped_when_creating_stock_count(): void
    {
        $this->createSkuWithBalance(quantity: 10);

        $deletedProduct = Product::query()->create([
            'product_code' => 'TUI-VAI',
            'name' => 'Túi vải',
            'active' => true,
        ]);
        $deletedProductSku = ProductSku::query()->create([
            'sku_code' => 'TUI-VAI-L',
            'product_id' => $deletedProduct->id,
            'size' => 'L',
            'active' => true,
        ]);
        InventoryBalance::query()->create(['sku_id' => $deletedProductSku->id, 'quantity' => 6]);
        $deletedProduct->delete();

        $response = $this->actingWithInventoryCookie()
            ->postJson('/api/v1/stock-counts', [], $this->authHeaders());

        $response->assertStatus(201);
        $this->assertDatabaseMissing('stock_count_lines', ['sku_id' => $deletedProductSku->id]);
        $this->assertDatabaseHas('stock_count_lines', ['sku_code' => 'AO-THUN-M']);
    }

    public function test_create_stock_count_requires_csrf(): void
    {
        $this->createSkuWithBalance(quantity: 10);

        $this->actingWithInventoryCookie()
            ->postJson('/api/v1/stock-counts', ['count_date' => '2026-08-08'])
            ->assertStatus(419);
    }

    public function test_staff_can_create_stock_count(): void
    {
        $this->createSkuWithBalance(quantity: 10);

        $response = $this->actingWithInventoryCookie(role: 'STAFF')
            ->postJson('/api/v1/stock-counts', [], $this->authHeaders());

        $response->assertStatus(201);
    }

    public function test_create_returns_409_when_no_active_sku_exists(): void
    {
        $response = $this->actingWithInventoryCookie()
            ->postJson('/api/v1/stock-counts', [], $this->authHeaders());

        $response->assertStatus(409);
        $response->assertJsonPath('message', 'Phiên kiểm kho chưa có SKU đang hoạt động để kiểm.');
    }

    public function test_list_stock_counts_returns_newest_first(): void
    {
        $this->createSkuWithBalance(quantity: 10);

        $this->actingWithInventoryCookie()
            ->postJson('/api/v1/stock-counts', ['name' => 'Phiên A'], $this->authHeaders())
            ->assertStatus(201);

        $this->actingWithInventoryCookie()
            ->postJson('/api/v1/stock-counts', ['name' => 'Phiên B'], $this->authHeaders())
            ->assertStatus(201);

        $response = $this->actingWithInventoryCookie()
            ->getJson('/api/v1/stock-counts');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'stock_counts');

        $names = array_column($response->json('stock_counts'), 'name');
        self::assertSame('Phiên B', $names[0]);
        self::assertSame('Phiên A', $names[1]);
    }

    public function test_list_stock_counts_does_not_include_lines(): void
    {
        $this->createSkuWithBalance(quantity: 10);

        $this->actingWithInventoryCookie()
            ->postJson('/api/v1/stock-counts', [], $this->authHeaders())
            ->assertStatus(201);

        $response = $this->actingWithInventoryCookie()
            ->getJson('/api/v1/stock-counts');

        $response->assertStatus(200);
        $items = $response->json('stock_counts');
        self::assertArrayNotHasKey('lines', $items[0]);
    }

    private function createSkuWithBalance(int $quantity): ProductSku
    {
        $product = Product::query()->firstOrCreate(
            ['product_code' => 'AO-THUN'],
            ['name' => 'Áo thun', 'active' => true],
        );

        $sku = ProductSku::query()->firstOrCreate(
            ['sku_code' => 'AO-THUN-M'],
            ['product_id' => $product->id, 'size' => 'M', 'active' => true],
        );

        InventoryBalance::query()->updateOrCreate(
            ['sku_id' => $sku->id],
            ['quantity' => $quantity],
        );

        return $sku;
    }

    private function createStockCount(?string $name = null, StockCountStatus $status = StockCountStatus::Draft): StockCount
    {
        return StockCount::query()->create([
            'name' => $name,
            'count_date' => '2026-08-08',
            'status' => $status->value,
            'created_by' => 10,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function lineAttributes(ProductSku $sku): array
    {
        return [
            'sku_id' => $sku->id,
            'sku_code' => $sku->sku_code,
            'product_id' => $sku->product_id,
            'product_code' => $sku->product->product_code,
            'product_name' => $sku->product->name,
            'size' => $sku->size,
            'expected_quantity' => 10,
        ];
    }

    private function columnIsNullable(string $table, string $column): bool
    {
        if (DB::getDriverName() === 'pgsql') {
            $definition = DB::selectOne(
                'select is_nullable from information_schema.columns where table_name = ? and column_name = ?',
                [$table, $column],
            );

            if ($definition !== null) {
                return $definition->is_nullable === 'YES';
            }

            self::fail("Không tìm thấy cột {$table}.{$column}.");
        }

        foreach (DB::select("PRAGMA table_info('{$table}')") as $definition) {
            if ($definition->name === $column) {
                return (int) $definition->notnull === 0;
            }
        }

        self::fail("Không tìm thấy cột {$table}.{$column}.");
    }

    private function columnLength(string $table, string $column): int
    {
        if (DB::getDriverName() === 'pgsql') {
            $definition = DB::selectOne(
                'select character_maximum_length from information_schema.columns where table_name = ? and column_name = ?',
                [$table, $column],
            );

            if ($definition !== null) {
                return (int) $definition->character_maximum_length;
            }

            self::fail("Không tìm thấy cột {$table}.{$column}.");
        }

        foreach (DB::select("PRAGMA table_info('{$table}')") as $definition) {
            if ($definition->name === $column && preg_match('/varchar\((\d+)\)/i', $definition->type, $matches) === 1) {
                return (int) $matches[1];
            }
        }

        self::fail("Không tìm thấy độ dài cột {$table}.{$column}.");
    }

    private function indexExists(string $table, string $index): bool
    {
        if (DB::getDriverName() === 'pgsql') {
            return DB::selectOne(
                'select indexname from pg_indexes where tablename = ? and indexname = ?',
                [$table, $index],
            ) !== null;
        }

        foreach (DB::select("PRAGMA index_list('{$table}')") as $definition) {
            if ($definition->name === $index) {
                return true;
            }
        }

        return false;
    }
}
