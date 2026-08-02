<?php

namespace Tests\Feature;

use App\Domain\Enums\InventoryTransactionType;
use App\Models\Product;
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

    private function authHeaders(): array
    {
        return ['X-CSRF-TOKEN' => 'inventory-csrf'];
    }

    private function actingWithInventoryCookie(string $role = 'STORE_MANAGER'): self
    {
        return $this
            ->withCredentials()
            ->withUnencryptedCookie('lm_access_token', $this->issueToken(role: $role))
            ->withUnencryptedCookie('lm_csrf_token', 'inventory-csrf');
    }

    private function issueToken(
        int $userId = 1,
        string $role = 'STORE_MANAGER',
        ?int $expiresAt = null,
    ): string {
        $now = time();
        $expiresAt ??= $now + 900;

        $header = $this->base64UrlEncode(json_encode([
            'alg' => 'HS256',
            'typ' => 'JWT',
        ], JSON_THROW_ON_ERROR));

        $payload = $this->base64UrlEncode(json_encode([
            'sub' => $userId,
            'role' => $role,
            'iss' => config('services.jwt.issuer'),
            'aud' => config('services.jwt.audience'),
            'iat' => $now,
            'exp' => $expiresAt,
        ], JSON_THROW_ON_ERROR));

        $signature = $this->base64UrlEncode(hash_hmac(
            'sha256',
            $header.'.'.$payload,
            (string) config('services.jwt.secret'),
            true,
        ));

        return $header.'.'.$payload.'.'.$signature;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
