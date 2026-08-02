<?php

namespace Tests\Feature;

use App\Domain\Enums\InventoryTransactionType;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ProductInventoryTest extends InventoryFeatureTestCase
{
    public function test_inventory_catalog_tables_exist(): void
    {
        self::assertTrue(Schema::hasTable('products'));
        self::assertTrue(Schema::hasTable('product_skus'));
        self::assertTrue(Schema::hasTable('inventory_balances'));
        self::assertTrue(Schema::hasTable('inventory_transactions'));

        self::assertTrue(Schema::hasColumns('inventory_balances', [
            'sku_id',
            'quantity',
        ]));
        self::assertTrue(Schema::hasColumns('inventory_transactions', [
            'quantity_change',
            'quantity_before',
            'quantity_after',
        ]));
    }

    public function test_inventory_balance_cannot_be_negative(): void
    {
        $productId = DB::table('products')->insertGetId([
            'product_code' => 'AO-THUN',
            'name' => 'Áo thun',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $skuId = DB::table('product_skus')->insertGetId([
            'product_id' => $productId,
            'sku_code' => 'AO-THUN-M',
            'size' => 'M',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('inventory_balances')->insert([
            'sku_id' => $skuId,
            'quantity' => -1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_product_sku_balance_and_transaction_relationships_work(): void
    {
        $product = Product::query()->create([
            'product_code' => 'AO-THUN',
            'name' => 'Áo thun',
            'active' => true,
        ]);

        $sku = $product->skus()->create([
            'sku_code' => 'AO-THUN-M',
            'size' => 'M',
            'active' => true,
        ]);

        $sku->balance()->create(['quantity' => 0]);
        $sku->transactions()->create([
            'type' => InventoryTransactionType::ImportSync->value,
            'quantity_change' => 0,
            'quantity_before' => 0,
            'quantity_after' => 0,
            'reason' => 'Tạo dữ liệu kiểm thử',
            'created_by' => 10,
        ]);

        $sku->refresh();

        self::assertSame('AO-THUN', $sku->product->product_code);
        self::assertSame(0, $sku->balance->quantity);
        self::assertSame('IMPORT_SYNC', $sku->transactions()->first()->type->value);
    }
}
