<?php

namespace Tests\Feature;

use App\Domain\Enums\InventoryTransactionType;
use App\Models\Product;

final class InventoryQueryTest extends InventoryFeatureTestCase
{
    public function test_inventory_list_returns_flat_balance_rows(): void
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
        $sku->balance()->create(['quantity' => 9]);

        $this->actingWithInventoryCookie()
            ->getJson('/api/v1/inventory')
            ->assertOk()
            ->assertJsonPath('inventory.0.sku_id', $sku->id)
            ->assertJsonPath('inventory.0.sku_code', 'AO-THUN-M')
            ->assertJsonPath('inventory.0.product_code', 'AO-THUN')
            ->assertJsonPath('inventory.0.product_name', 'Áo thun')
            ->assertJsonPath('inventory.0.quantity', 9);
    }

    public function test_inventory_list_supports_search_by_product_code_name_or_sku_code(): void
    {
        $shirt = Product::query()->create([
            'product_code' => 'AO-THUN',
            'name' => 'Áo thun',
            'active' => true,
        ]);
        $shirtSku = $shirt->skus()->create([
            'sku_code' => 'AO-THUN-M',
            'size' => 'M',
            'active' => true,
        ]);
        $shirtSku->balance()->create(['quantity' => 4]);

        $pants = Product::query()->create([
            'product_code' => 'QUAN-JEAN',
            'name' => 'Quần jean',
            'active' => true,
        ]);
        $pantsSku = $pants->skus()->create([
            'sku_code' => 'QUAN-JEAN-32',
            'size' => '32',
            'active' => true,
        ]);
        $pantsSku->balance()->create(['quantity' => 6]);

        $this->actingWithInventoryCookie()
            ->getJson('/api/v1/inventory?search=AO-THUN')
            ->assertOk()
            ->assertJsonCount(1, 'inventory')
            ->assertJsonPath('inventory.0.product_code', 'AO-THUN');

        $this->actingWithInventoryCookie()
            ->getJson('/api/v1/inventory?search=Quần')
            ->assertOk()
            ->assertJsonCount(1, 'inventory')
            ->assertJsonPath('inventory.0.product_code', 'QUAN-JEAN');

        $this->actingWithInventoryCookie()
            ->getJson('/api/v1/inventory?search=JEAN-32')
            ->assertOk()
            ->assertJsonCount(1, 'inventory')
            ->assertJsonPath('inventory.0.sku_code', 'QUAN-JEAN-32');
    }

    public function test_transaction_history_is_newest_first(): void
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
        $sku->balance()->create(['quantity' => 7]);
        $sku->transactions()->create([
            'type' => InventoryTransactionType::ImportSync->value,
            'quantity_change' => 5,
            'quantity_before' => 0,
            'quantity_after' => 5,
            'reference_type' => 'manual_test',
            'reference_id' => 'seed-1',
            'reason' => 'Tạo dữ liệu kiểm thử',
            'created_by' => 10,
            'created_at' => now()->subMinute(),
        ]);
        $sku->transactions()->create([
            'type' => InventoryTransactionType::ImportSync->value,
            'quantity_change' => 2,
            'quantity_before' => 5,
            'quantity_after' => 7,
            'reference_type' => 'manual_test',
            'reference_id' => 'seed-2',
            'reason' => 'Đồng bộ tồn kho',
            'created_by' => 10,
            'created_at' => now(),
        ]);

        $this->actingWithInventoryCookie()
            ->getJson("/api/v1/inventory/{$sku->id}/transactions")
            ->assertOk()
            ->assertJsonPath('transactions.0.quantity_after', 7)
            ->assertJsonPath('transactions.1.quantity_after', 5);
    }

    public function test_transaction_history_for_missing_sku_returns_404(): void
    {
        $response = $this->actingWithInventoryCookie()
            ->getJson('/api/v1/inventory/999999/transactions')
            ->assertNotFound();

        self::assertStringContainsString('ProductSku', $response->json('message'));
    }
}
