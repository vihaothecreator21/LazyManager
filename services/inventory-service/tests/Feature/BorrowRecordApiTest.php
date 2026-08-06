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
