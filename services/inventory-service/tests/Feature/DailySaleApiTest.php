<?php

namespace Tests\Feature;

use App\Domain\Enums\DailySaleStatus;
use App\Models\DailySale;
use App\Models\DailySaleLine;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\ProductSku;
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

    private function createSkuWithBalance(int $quantity): ProductSku
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
        InventoryBalance::query()->create([
            'sku_id' => $sku->id,
            'quantity' => $quantity,
        ]);

        return $sku;
    }
}
