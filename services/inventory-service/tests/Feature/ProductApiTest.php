<?php

namespace Tests\Feature;

use App\Models\Product;

final class ProductApiTest extends InventoryFeatureTestCase
{
    public function test_authenticated_user_can_create_product(): void
    {
        $this->actingWithInventoryCookie()
            ->withHeaders($this->authHeaders())
            ->postJson('/api/v1/products', [
                'product_code' => '  ao-thun  ',
                'name' => 'Áo thun',
            ])
            ->assertCreated()
            ->assertJsonPath('product.product_code', 'AO-THUN')
            ->assertJsonPath('product.name', 'Áo thun')
            ->assertJsonPath('product.active', true);

        $this->assertDatabaseHas('products', [
            'product_code' => 'AO-THUN',
            'name' => 'Áo thun',
            'active' => true,
        ]);
    }

    public function test_staff_role_can_create_and_manage_products(): void
    {
        $response = $this->actingWithInventoryCookie('STAFF')
            ->withHeaders($this->authHeaders())
            ->postJson('/api/v1/products', [
                'product_code' => 'AO-KHOAC',
                'name' => 'Áo khoác',
            ])
            ->assertCreated();

        $productId = $response->json('product.id');

        $this->actingWithInventoryCookie('STAFF')
            ->withHeaders($this->authHeaders())
            ->putJson("/api/v1/products/{$productId}", [
                'name' => 'Áo khoác nhẹ',
            ])
            ->assertOk()
            ->assertJsonPath('product.name', 'Áo khoác nhẹ');

        $this->actingWithInventoryCookie('STAFF')
            ->withHeaders($this->authHeaders())
            ->deleteJson("/api/v1/products/{$productId}")
            ->assertOk()
            ->assertJsonPath('product.active', false);
    }

    public function test_product_code_must_be_unique(): void
    {
        Product::query()->create([
            'product_code' => 'AO-THUN',
            'name' => 'Áo thun',
            'active' => true,
        ]);

        $this->actingWithInventoryCookie()
            ->withHeaders($this->authHeaders())
            ->postJson('/api/v1/products', [
                'product_code' => 'AO-THUN',
                'name' => 'Áo thun trùng',
            ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Mã sản phẩm đã tồn tại.');
    }

    public function test_cannot_reuse_soft_deleted_product_code(): void
    {
        $product = Product::query()->create([
            'product_code' => 'AO-THUN',
            'name' => 'Áo thun',
            'active' => false,
        ]);
        $product->delete();

        $this->actingWithInventoryCookie()
            ->withHeaders($this->authHeaders())
            ->postJson('/api/v1/products', [
                'product_code' => 'AO-THUN',
                'name' => 'Áo thun mới',
            ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Mã sản phẩm đã tồn tại.');
    }

    public function test_product_code_is_normalized_to_uppercase_and_trimmed(): void
    {
        $this->actingWithInventoryCookie()
            ->withHeaders($this->authHeaders())
            ->postJson('/api/v1/products', [
                'product_code' => '  ao-thun  ',
                'name' => 'Áo thun',
            ])
            ->assertCreated()
            ->assertJsonPath('product.product_code', 'AO-THUN');
    }

    public function test_authenticated_user_can_list_products_with_skus(): void
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
        $sku->balance()->create(['quantity' => 3]);

        $this->actingWithInventoryCookie()
            ->getJson('/api/v1/products?search=thun')
            ->assertOk()
            ->assertJsonPath('products.0.product_code', 'AO-THUN')
            ->assertJsonPath('products.0.skus.0.sku_code', 'AO-THUN-M')
            ->assertJsonPath('products.0.skus.0.quantity', 3);
    }

    public function test_authenticated_user_can_update_product(): void
    {
        $product = Product::query()->create([
            'product_code' => 'AO-THUN',
            'name' => 'Áo thun',
            'active' => true,
        ]);

        $this->actingWithInventoryCookie()
            ->withHeaders($this->authHeaders())
            ->putJson("/api/v1/products/{$product->id}", [
                'product_code' => ' ao-so-mi ',
                'name' => 'Áo sơ mi',
                'active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('product.product_code', 'AO-SO-MI')
            ->assertJsonPath('product.name', 'Áo sơ mi')
            ->assertJsonPath('product.active', true);
    }

    public function test_authenticated_user_can_deactivate_product(): void
    {
        $product = Product::query()->create([
            'product_code' => 'AO-THUN',
            'name' => 'Áo thun',
            'active' => true,
        ]);

        $this->actingWithInventoryCookie()
            ->withHeaders($this->authHeaders())
            ->deleteJson("/api/v1/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('product.active', false);

        self::assertNotNull(Product::withTrashed()->find($product->id)->deleted_at);
    }

    public function test_deactivating_product_deactivates_and_soft_deletes_all_associated_skus(): void
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
        $sku->balance()->create(['quantity' => 5]);

        $this->actingWithInventoryCookie()
            ->withHeaders($this->authHeaders())
            ->deleteJson("/api/v1/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('product.skus.0.active', false)
            ->assertJsonPath('product.skus.0.quantity', 5);

        $deletedSku = $product->skus()->withTrashed()->first();
        self::assertFalse($deletedSku->active);
        self::assertNotNull($deletedSku->deleted_at);
        $this->assertDatabaseHas('inventory_balances', [
            'sku_id' => $sku->id,
            'quantity' => 5,
        ]);
    }

    public function test_get_product_requests_do_not_require_csrf(): void
    {
        Product::query()->create([
            'product_code' => 'AO-THUN',
            'name' => 'Áo thun',
            'active' => true,
        ]);

        $this->actingWithInventoryCookie()
            ->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonPath('products.0.product_code', 'AO-THUN');
    }

    public function test_product_mutation_without_csrf_returns_419(): void
    {
        $this->actingWithInventoryCookie()
            ->postJson('/api/v1/products', [
                'product_code' => 'AO-THUN',
                'name' => 'Áo thun',
            ])
            ->assertStatus(419);
    }
}
