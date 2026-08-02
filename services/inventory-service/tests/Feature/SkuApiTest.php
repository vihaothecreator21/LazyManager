<?php

namespace Tests\Feature;

use App\Models\Product;

final class SkuApiTest extends InventoryFeatureTestCase
{
    public function test_authenticated_user_can_create_sku_and_zero_balance_is_created(): void
    {
        $product = Product::query()->create([
            'product_code' => 'AO-THUN',
            'name' => 'Áo thun',
            'active' => true,
        ]);

        $response = $this->actingWithInventoryCookie()
            ->withHeaders($this->authHeaders())
            ->postJson("/api/v1/products/{$product->id}/skus", [
                'sku_code' => '  ao-thun-m  ',
                'size' => 'M',
            ])
            ->assertCreated()
            ->assertJsonPath('sku.sku_code', 'AO-THUN-M')
            ->assertJsonPath('sku.quantity', 0);

        $this->assertDatabaseHas('inventory_balances', [
            'sku_id' => $response->json('sku.id'),
            'quantity' => 0,
        ]);
    }

    public function test_sku_code_is_normalized_to_uppercase_and_trimmed(): void
    {
        $product = Product::query()->create([
            'product_code' => 'AO-THUN',
            'name' => 'Áo thun',
            'active' => true,
        ]);

        $this->actingWithInventoryCookie()
            ->withHeaders($this->authHeaders())
            ->postJson("/api/v1/products/{$product->id}/skus", [
                'sku_code' => '  ao-thun-m  ',
                'size' => 'M',
            ])
            ->assertCreated()
            ->assertJsonPath('sku.sku_code', 'AO-THUN-M');
    }

    public function test_sku_code_must_be_unique(): void
    {
        $product = Product::query()->create([
            'product_code' => 'AO-THUN',
            'name' => 'Áo thun',
            'active' => true,
        ]);
        $product->skus()->create([
            'sku_code' => 'AO-THUN-M',
            'size' => 'M',
            'active' => true,
        ]);

        $this->actingWithInventoryCookie()
            ->withHeaders($this->authHeaders())
            ->postJson("/api/v1/products/{$product->id}/skus", [
                'sku_code' => 'AO-THUN-M',
                'size' => 'L',
            ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Mã SKU đã tồn tại.');
    }

    public function test_cannot_reuse_soft_deleted_sku_code(): void
    {
        $product = Product::query()->create([
            'product_code' => 'AO-THUN',
            'name' => 'Áo thun',
            'active' => true,
        ]);
        $sku = $product->skus()->create([
            'sku_code' => 'AO-THUN-M',
            'size' => 'M',
            'active' => false,
        ]);
        $sku->delete();

        $this->actingWithInventoryCookie()
            ->withHeaders($this->authHeaders())
            ->postJson("/api/v1/products/{$product->id}/skus", [
                'sku_code' => 'AO-THUN-M',
                'size' => 'L',
            ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Mã SKU đã tồn tại.');
    }

    public function test_cannot_create_sku_for_inactive_product(): void
    {
        $product = Product::query()->create([
            'product_code' => 'AO-THUN',
            'name' => 'Áo thun',
            'active' => false,
        ]);

        $this->actingWithInventoryCookie()
            ->withHeaders($this->authHeaders())
            ->postJson("/api/v1/products/{$product->id}/skus", [
                'sku_code' => 'AO-THUN-M',
                'size' => 'M',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Không thể tạo SKU cho sản phẩm không còn hoạt động.');
    }

    public function test_authenticated_user_can_update_sku(): void
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
        $sku->balance()->create(['quantity' => 4]);

        $this->actingWithInventoryCookie()
            ->withHeaders($this->authHeaders())
            ->putJson("/api/v1/skus/{$sku->id}", [
                'sku_code' => ' ao-thun-l ',
                'size' => 'L',
                'active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('sku.sku_code', 'AO-THUN-L')
            ->assertJsonPath('sku.size', 'L')
            ->assertJsonPath('sku.active', true)
            ->assertJsonPath('sku.quantity', 4);
    }

    public function test_authenticated_user_can_deactivate_sku_without_deleting_balance(): void
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

        $this->actingWithInventoryCookie()
            ->withHeaders($this->authHeaders())
            ->deleteJson("/api/v1/skus/{$sku->id}")
            ->assertOk()
            ->assertJsonPath('sku.active', false)
            ->assertJsonPath('sku.quantity', 7);

        $deletedSku = $product->skus()->withTrashed()->first();
        self::assertFalse($deletedSku->active);
        self::assertNotNull($deletedSku->deleted_at);
        $this->assertDatabaseHas('inventory_balances', [
            'sku_id' => $sku->id,
            'quantity' => 7,
        ]);
    }
}
