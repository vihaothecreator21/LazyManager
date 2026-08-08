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
