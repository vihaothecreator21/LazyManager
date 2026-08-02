<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class ProductInventoryTest extends TestCase
{
    use RefreshDatabase;

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
}
