<?php

namespace App\Domain\Services;

use App\Domain\Enums\InventoryTransactionType;
use App\Domain\Exceptions\InsufficientStockException;
use App\Domain\Exceptions\InventoryBusinessException;
use App\Models\InventoryBalance;
use App\Models\InventoryTransaction;
use App\Models\ProductSku;
use Illuminate\Support\Facades\DB;

final class InventoryBalanceService
{
    public function increase(
        ProductSku $sku,
        int $quantity,
        InventoryTransactionType $type,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $reason = null,
        ?int $createdBy = null,
    ): InventoryTransaction {
        $this->assertPositiveQuantity($quantity);

        return $this->applyDelta($sku, $quantity, $type, $referenceType, $referenceId, $reason, $createdBy);
    }

    public function decrease(
        ProductSku $sku,
        int $quantity,
        InventoryTransactionType $type,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $reason = null,
        ?int $createdBy = null,
    ): InventoryTransaction {
        $this->assertPositiveQuantity($quantity);

        return $this->applyDelta($sku, -$quantity, $type, $referenceType, $referenceId, $reason, $createdBy);
    }

    public function synchronize(
        ProductSku $sku,
        int $targetQuantity,
        InventoryTransactionType $type,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $reason = null,
        ?int $createdBy = null,
    ): InventoryTransaction {
        if ($targetQuantity < 0) {
            throw new InventoryBusinessException('Số lượng tồn kho không được âm.');
        }

        if ($type !== InventoryTransactionType::ImportSync) {
            throw new InventoryBusinessException('Phương thức đồng bộ tồn kho chỉ chấp nhận loại giao dịch IMPORT_SYNC.');
        }

        return $this->applyTarget($sku, $targetQuantity, $type, $referenceType, $referenceId, $reason, $createdBy);
    }

    private function applyDelta(
        ProductSku $sku,
        int $delta,
        InventoryTransactionType $type,
        ?string $referenceType,
        ?string $referenceId,
        ?string $reason,
        ?int $createdBy,
    ): InventoryTransaction {
        return DB::transaction(function () use ($sku, $delta, $type, $referenceType, $referenceId, $reason, $createdBy): InventoryTransaction {
            $balance = $this->lockedBalance($sku);
            $quantityBefore = $balance->quantity;
            $quantityAfter = $quantityBefore + $delta;

            if ($quantityAfter < 0) {
                throw new InsufficientStockException();
            }

            $balance->quantity = $quantityAfter;
            $balance->save();

            return $this->createTransaction(
                $sku,
                $type,
                $delta,
                $quantityBefore,
                $quantityAfter,
                $referenceType,
                $referenceId,
                $reason,
                $createdBy,
            );
        });
    }

    private function applyTarget(
        ProductSku $sku,
        int $targetQuantity,
        InventoryTransactionType $type,
        ?string $referenceType,
        ?string $referenceId,
        ?string $reason,
        ?int $createdBy,
    ): InventoryTransaction {
        return DB::transaction(function () use ($sku, $targetQuantity, $type, $referenceType, $referenceId, $reason, $createdBy): InventoryTransaction {
            $balance = $this->lockedBalance($sku);
            $quantityBefore = $balance->quantity;
            $quantityAfter = $targetQuantity;
            $quantityChange = $quantityAfter - $quantityBefore;

            $balance->quantity = $quantityAfter;
            $balance->save();

            return $this->createTransaction(
                $sku,
                $type,
                $quantityChange,
                $quantityBefore,
                $quantityAfter,
                $referenceType,
                $referenceId,
                $reason,
                $createdBy,
            );
        });
    }

    private function assertPositiveQuantity(int $quantity): void
    {
        if ($quantity <= 0) {
            throw new InventoryBusinessException('Số lượng phải lớn hơn 0.');
        }
    }

    private function lockedBalance(ProductSku $sku): InventoryBalance
    {
        $balance = InventoryBalance::query()
            ->where('sku_id', $sku->id)
            ->lockForUpdate()
            ->first();

        if (! $balance instanceof InventoryBalance) {
            throw new InventoryBusinessException('Không tìm thấy dữ liệu tồn kho của SKU.');
        }

        return $balance;
    }

    private function createTransaction(
        ProductSku $sku,
        InventoryTransactionType $type,
        int $quantityChange,
        int $quantityBefore,
        int $quantityAfter,
        ?string $referenceType,
        ?string $referenceId,
        ?string $reason,
        ?int $createdBy,
    ): InventoryTransaction {
        return InventoryTransaction::query()->create([
            'sku_id' => $sku->id,
            'type' => $type->value,
            'quantity_change' => $quantityChange,
            'quantity_before' => $quantityBefore,
            'quantity_after' => $quantityAfter,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'reason' => $reason,
            'created_by' => $createdBy,
        ]);
    }
}
