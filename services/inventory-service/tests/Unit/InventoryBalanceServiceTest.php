<?php

namespace Tests\Unit;

use App\Domain\Enums\InventoryTransactionType;
use App\Domain\Exceptions\InsufficientStockException;
use App\Domain\Exceptions\InventoryBusinessException;
use App\Domain\Services\InventoryBalanceService;
use App\Models\Product;
use App\Models\ProductSku;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InventoryBalanceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_increase_updates_balance_and_creates_transaction(): void
    {
        $sku = $this->createSkuWithBalance(0);
        $service = new InventoryBalanceService();

        $transaction = $service->increase(
            sku: $sku,
            quantity: 5,
            type: InventoryTransactionType::ImportSync,
            referenceType: 'manual_test',
            referenceId: 'seed-1',
            reason: 'Tạo dữ liệu kiểm thử',
            createdBy: 10,
        );

        self::assertSame(0, $transaction->quantity_before);
        self::assertSame(5, $transaction->quantity_change);
        self::assertSame(5, $transaction->quantity_after);
        self::assertSame('manual_test', $transaction->reference_type);
        self::assertSame('seed-1', $transaction->reference_id);
        self::assertSame('Tạo dữ liệu kiểm thử', $transaction->reason);
        self::assertSame(10, $transaction->created_by);
        $this->assertDatabaseHas('inventory_balances', [
            'sku_id' => $sku->id,
            'quantity' => 5,
        ]);
    }

    public function test_decrease_updates_balance_and_creates_transaction(): void
    {
        $sku = $this->createSkuWithBalance(8);
        $service = new InventoryBalanceService();

        $transaction = $service->decrease(
            sku: $sku,
            quantity: 3,
            type: InventoryTransactionType::Sale,
            referenceType: 'sale',
            referenceId: 'sale-1',
            reason: 'Bán hàng',
            createdBy: 11,
        );

        self::assertSame(8, $transaction->quantity_before);
        self::assertSame(-3, $transaction->quantity_change);
        self::assertSame(5, $transaction->quantity_after);
        self::assertSame(InventoryTransactionType::Sale, $transaction->type);
        $this->assertDatabaseHas('inventory_balances', [
            'sku_id' => $sku->id,
            'quantity' => 5,
        ]);
    }

    public function test_decrease_more_than_balance_throws_and_rolls_back(): void
    {
        $sku = $this->createSkuWithBalance(2);
        $service = new InventoryBalanceService();

        $this->expectException(InsufficientStockException::class);
        $this->expectExceptionMessage('Tồn kho không đủ để thực hiện thao tác này.');

        try {
            $service->decrease(
                sku: $sku,
                quantity: 3,
                type: InventoryTransactionType::Sale,
            );
        } finally {
            $this->assertDatabaseHas('inventory_balances', [
                'sku_id' => $sku->id,
                'quantity' => 2,
            ]);
            $this->assertDatabaseCount('inventory_transactions', 0);
        }
    }

    public function test_synchronize_sets_absolute_quantity_and_creates_transaction(): void
    {
        $sku = $this->createSkuWithBalance(5);
        $service = new InventoryBalanceService();

        $transaction = $service->synchronize(
            sku: $sku,
            targetQuantity: 7,
            type: InventoryTransactionType::ImportSync,
            referenceType: 'manual_test',
            referenceId: 'seed-2',
            reason: 'Đồng bộ tồn kho',
            createdBy: 12,
        );

        self::assertSame(5, $transaction->quantity_before);
        self::assertSame(2, $transaction->quantity_change);
        self::assertSame(7, $transaction->quantity_after);
        $this->assertDatabaseHas('inventory_balances', [
            'sku_id' => $sku->id,
            'quantity' => 7,
        ]);
    }

    public function test_quantity_must_be_positive_for_increase_and_decrease(): void
    {
        $sku = $this->createSkuWithBalance(5);
        $service = new InventoryBalanceService();

        try {
            $service->increase($sku, 0, InventoryTransactionType::ImportSync);
            self::fail('Increase phải từ chối số lượng không dương.');
        } catch (InventoryBusinessException $e) {
            self::assertSame('Số lượng phải lớn hơn 0.', $e->getMessage());
        }

        try {
            $service->decrease($sku, 0, InventoryTransactionType::Sale);
            self::fail('Decrease phải từ chối số lượng không dương.');
        } catch (InventoryBusinessException $e) {
            self::assertSame('Số lượng phải lớn hơn 0.', $e->getMessage());
        }

        $this->assertDatabaseHas('inventory_balances', [
            'sku_id' => $sku->id,
            'quantity' => 5,
        ]);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_missing_balance_throws_business_error_without_creating_balance(): void
    {
        $sku = $this->createSkuWithoutBalance();
        $service = new InventoryBalanceService();

        $this->expectException(InventoryBusinessException::class);
        $this->expectExceptionMessage('Không tìm thấy dữ liệu tồn kho của SKU.');

        try {
            $service->increase(
                sku: $sku,
                quantity: 5,
                type: InventoryTransactionType::ImportSync,
            );
        } finally {
            $this->assertDatabaseMissing('inventory_balances', [
                'sku_id' => $sku->id,
            ]);
            $this->assertDatabaseCount('inventory_transactions', 0);
        }
    }

    private function createSkuWithBalance(int $quantity): ProductSku
    {
        $sku = $this->createSkuWithoutBalance();
        $sku->balance()->create(['quantity' => $quantity]);

        return $sku;
    }

    private function createSkuWithoutBalance(): ProductSku
    {
        $product = Product::query()->create([
            'product_code' => 'AO-THUN',
            'name' => 'Áo thun',
            'active' => true,
        ]);

        return $product->skus()->create([
            'sku_code' => 'AO-THUN-M',
            'size' => 'M',
            'active' => true,
        ]);
    }
}
