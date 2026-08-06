<?php

namespace Tests\Feature;

use App\Domain\Enums\BorrowRecordStatus;
use App\Models\BorrowRecord;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\ProductSku;
use DateTimeInterface;
use Illuminate\Support\Facades\Schema;

final class BorrowRecordApiTest extends InventoryFeatureTestCase
{
    public function test_borrow_records_table_exists(): void
    {
        self::assertTrue(Schema::hasTable('borrow_records'));
        self::assertTrue(Schema::hasColumns('borrow_records', [
            'sku_id',
            'quantity',
            'status',
            'borrower_name',
            'borrow_location',
            'note',
            'return_note',
            'created_by',
            'returned_by',
            'borrowed_at',
            'returned_at',
        ]));
    }

    public function test_borrow_record_model_relationships_work(): void
    {
        $sku = $this->createSkuWithBalance(quantity: 10);

        $borrowRecord = BorrowRecord::query()->create([
            'sku_id' => $sku->id,
            'quantity' => 2,
            'status' => BorrowRecordStatus::Borrowed->value,
            'borrower_name' => 'Lan cửa hàng A',
            'borrow_location' => 'Quầy pop-up cuối tuần',
            'borrowed_at' => now(),
            'created_by' => 10,
        ]);

        self::assertSame('AO-THUN-M', $borrowRecord->sku->sku_code);
        self::assertSame(BorrowRecordStatus::Borrowed, $borrowRecord->status);
    }

    public function test_authenticated_user_can_create_borrow_record_and_decrement_stock(): void
    {
        $sku = $this->createSkuWithBalance(quantity: 10);

        $this->actingWithInventoryCookie(userId: 10)
            ->withHeaders($this->authHeaders())
            ->postJson('/api/v1/borrow-records', [
                'sku_id' => $sku->id,
                'quantity' => 2,
                'borrower_name' => 'Lan cửa hàng A',
                'borrow_location' => 'Quầy pop-up cuối tuần',
                'note' => 'Mang đi chụp mẫu',
            ])
            ->assertCreated()
            ->assertJsonPath('borrow_record.status', 'BORROWED')
            ->assertJsonPath('borrow_record.sku_code', 'AO-THUN-M')
            ->assertJsonPath('borrow_record.quantity', 2)
            ->assertJsonPath('borrow_record.created_by', 10);

        $this->assertDatabaseHas('borrow_records', [
            'sku_id' => $sku->id,
            'quantity' => 2,
            'status' => 'BORROWED',
            'borrower_name' => 'Lan cửa hàng A',
            'borrow_location' => 'Quầy pop-up cuối tuần',
            'note' => 'Mang đi chụp mẫu',
            'created_by' => 10,
        ]);
        $this->assertDatabaseHas('inventory_balances', [
            'sku_id' => $sku->id,
            'quantity' => 8,
        ]);
        $this->assertDatabaseHas('inventory_transactions', [
            'sku_id' => $sku->id,
            'type' => 'BORROW_OUT',
            'quantity_before' => 10,
            'quantity_change' => -2,
            'quantity_after' => 8,
            'reference_type' => 'borrow_record',
            'reason' => 'Cho mượn: Quầy pop-up cuối tuần',
            'created_by' => 10,
        ]);
    }

    public function test_borrow_record_creation_rolls_back_when_stock_is_insufficient(): void
    {
        $sku = $this->createSkuWithBalance(quantity: 1, skuCode: 'AO-THUN-S');

        $this->actingWithInventoryCookie(userId: 10)
            ->withHeaders($this->authHeaders())
            ->postJson('/api/v1/borrow-records', [
                'sku_id' => $sku->id,
                'quantity' => 2,
                'borrower_name' => 'Lan cửa hàng A',
                'borrow_location' => 'Quầy pop-up cuối tuần',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Không đủ tồn kho để cho mượn sản phẩm.');

        $this->assertDatabaseMissing('borrow_records', [
            'sku_id' => $sku->id,
        ]);
        $this->assertDatabaseHas('inventory_balances', [
            'sku_id' => $sku->id,
            'quantity' => 1,
        ]);
        $this->assertDatabaseMissing('inventory_transactions', [
            'sku_id' => $sku->id,
            'type' => 'BORROW_OUT',
        ]);
    }

    public function test_borrow_record_creation_blocks_inactive_sku(): void
    {
        $sku = $this->createSkuWithBalance(quantity: 10, active: false, skuCode: 'AO-THUN-INACTIVE');

        $this->actingWithInventoryCookie(userId: 10)
            ->withHeaders($this->authHeaders())
            ->postJson('/api/v1/borrow-records', [
                'sku_id' => $sku->id,
                'quantity' => 2,
                'borrower_name' => 'Lan cửa hàng A',
                'borrow_location' => 'Quầy pop-up cuối tuần',
            ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'SKU đã ngừng hoạt động và không thể cho mượn.');

        $this->assertDatabaseMissing('borrow_records', [
            'sku_id' => $sku->id,
        ]);
        $this->assertDatabaseHas('inventory_balances', [
            'sku_id' => $sku->id,
            'quantity' => 10,
        ]);
    }

    public function test_borrow_record_creation_requires_positive_quantity(): void
    {
        $sku = $this->createSkuWithBalance(quantity: 10);

        $this->actingWithInventoryCookie(userId: 10)
            ->withHeaders($this->authHeaders())
            ->postJson('/api/v1/borrow-records', [
                'sku_id' => $sku->id,
                'quantity' => 0,
                'borrower_name' => 'Lan cửa hàng A',
                'borrow_location' => 'Quầy pop-up cuối tuần',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');
    }

    public function test_borrow_record_creation_requires_csrf(): void
    {
        $sku = $this->createSkuWithBalance(quantity: 10);

        $this->actingWithInventoryCookie(userId: 10)
            ->postJson('/api/v1/borrow-records', [
                'sku_id' => $sku->id,
                'quantity' => 2,
                'borrower_name' => 'Lan cửa hàng A',
                'borrow_location' => 'Quầy pop-up cuối tuần',
            ])
            ->assertStatus(419);
    }

    public function test_staff_can_create_borrow_record(): void
    {
        $sku = $this->createSkuWithBalance(quantity: 10);

        $this->actingWithInventoryCookie(role: 'STAFF', userId: 12)
            ->withHeaders($this->authHeaders())
            ->postJson('/api/v1/borrow-records', [
                'sku_id' => $sku->id,
                'quantity' => 3,
                'borrower_name' => 'Nhân sự cửa hàng B',
                'borrow_location' => 'Kệ trưng bày tạm',
            ])
            ->assertCreated()
            ->assertJsonPath('borrow_record.created_by', 12);

        $this->assertDatabaseHas('inventory_balances', [
            'sku_id' => $sku->id,
            'quantity' => 7,
        ]);
    }

    private function createSkuWithBalance(int $quantity, bool $active = true, string $skuCode = 'AO-THUN-M'): ProductSku
    {
        $product = Product::query()->firstOrCreate(
            ['product_code' => 'AO-THUN'],
            ['name' => 'Áo thun', 'active' => true],
        );
        $sku = ProductSku::query()->firstOrCreate(
            ['sku_code' => $skuCode],
            ['product_id' => $product->id, 'size' => 'M', 'active' => $active],
        );
        InventoryBalance::query()->updateOrCreate(
            ['sku_id' => $sku->id],
            ['quantity' => $quantity],
        );

        return $sku;
    }

    private function createBorrowRecord(
        ?ProductSku $sku = null,
        int $quantity = 2,
        string $borrowerName = 'Lan cửa hàng A',
        string $borrowLocation = 'Quầy pop-up cuối tuần',
        ?DateTimeInterface $borrowedAt = null,
    ): BorrowRecord {
        $sku ??= $this->createSkuWithBalance(quantity: 10);

        return BorrowRecord::query()->create([
            'sku_id' => $sku->id,
            'quantity' => $quantity,
            'status' => BorrowRecordStatus::Borrowed->value,
            'borrower_name' => $borrowerName,
            'borrow_location' => $borrowLocation,
            'borrowed_at' => $borrowedAt ?? now(),
            'created_by' => 10,
        ]);
    }
}
