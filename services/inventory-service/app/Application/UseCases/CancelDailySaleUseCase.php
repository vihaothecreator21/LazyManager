<?php

namespace App\Application\UseCases;

use App\Domain\Enums\DailySaleStatus;
use App\Domain\Enums\InventoryTransactionType;
use App\Domain\Exceptions\InventoryBusinessException;
use App\Domain\Services\InventoryBalanceService;
use App\Models\DailySale;
use App\Models\ProductSku;
use Illuminate\Support\Facades\DB;

final class CancelDailySaleUseCase
{
    public function __construct(private readonly InventoryBalanceService $balanceService)
    {
    }

    public function execute(DailySale $dailySale, string $reason, ?int $cancelledBy): DailySale
    {
        return DB::transaction(function () use ($dailySale, $reason, $cancelledBy): DailySale {
            // Lock row để tránh race condition
            $locked = DailySale::query()
                ->whereKey($dailySale->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Chỉ hủy từ CONFIRMED
            if ($locked->status === DailySaleStatus::Draft) {
                throw new InventoryBusinessException('Phiếu bán chưa xác nhận nên không thể hủy.', 409);
            }

            if ($locked->status === DailySaleStatus::Cancelled) {
                throw new InventoryBusinessException('Phiếu bán này đã bị hủy.', 409);
            }

            // Load lines
            $locked->load(['lines' => fn ($query) => $query->orderBy('row_number')]);

            // Revalidate SKU + increase tồn kho
            foreach ($locked->lines as $line) {
                // Query fresh ProductSku
                $sku = ProductSku::query()->whereKey($line->sku_id)->first();

                if (! $sku instanceof ProductSku || ! $sku->active || $sku->trashed() || $line->quantity_sold === null) {
                    throw new InventoryBusinessException("SKU {$line->sku_code} đã ngừng hoạt động và không thể hủy.", 409);
                }

                $this->balanceService->increase(
                    sku: $sku,
                    quantity: (int) $line->quantity_sold,
                    type: InventoryTransactionType::SaleReversal,
                    referenceType: 'daily_sale',
                    referenceId: (string) $locked->id,
                    reason: $reason,
                    createdBy: $cancelledBy,
                );
            }

            // Cập nhật trạng thái hủy
            $locked->status = DailySaleStatus::Cancelled;
            $locked->confirmed_sales_date = null;
            $locked->cancelled_at = now();
            $locked->cancelled_by = $cancelledBy;
            $locked->cancel_reason = $reason;
            $locked->save();

            return $locked->load(['lines' => fn ($query) => $query->orderBy('row_number')]);
        });
    }
}
