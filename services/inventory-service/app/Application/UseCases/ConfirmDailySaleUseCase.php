<?php

namespace App\Application\UseCases;

use App\Domain\Enums\DailySaleStatus;
use App\Domain\Enums\InventoryTransactionType;
use App\Domain\Exceptions\InsufficientStockException;
use App\Domain\Exceptions\InventoryBusinessException;
use App\Domain\Services\InventoryBalanceService;
use App\Models\DailySale;
use App\Models\DailySaleLine;
use App\Models\ProductSku;
use Illuminate\Support\Facades\DB;

final class ConfirmDailySaleUseCase
{
    public function __construct(private readonly InventoryBalanceService $balanceService)
    {
    }

    public function execute(DailySale $dailySale, ?int $confirmedBy): DailySale
    {
        try {
            return DB::transaction(function () use ($dailySale, $confirmedBy): DailySale {
                // Lock row để tránh race condition
                $locked = DailySale::query()
                    ->whereKey($dailySale->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                // Chỉ confirm từ DRAFT
                if ($locked->status === DailySaleStatus::Confirmed) {
                    throw new InventoryBusinessException('Phiếu này đã được xác nhận.', 409);
                }

                if ($locked->status === DailySaleStatus::Cancelled) {
                    throw new InventoryBusinessException('Phiếu đã hủy, không thể xác nhận.', 409);
                }

                // Load lines
                $locked->load(['lines' => fn ($query) => $query->orderBy('row_number')]);

                // Chặn nếu còn line error
                $hasErrors = $locked->lines->contains(
                    fn (DailySaleLine $line): bool => $line->error_message !== null,
                );
                if ($hasErrors) {
                    throw new InventoryBusinessException('Không thể xác nhận phiếu còn dòng lỗi.', 422);
                }

                // Chặn nếu đã có phiếu CONFIRMED cùng sales_date (ngoại trừ chính nó)
                $sameDateExists = DailySale::query()
                    ->where('id', '!=', $locked->id)
                    ->whereDate('confirmed_sales_date', $locked->sales_date)
                    ->where('status', DailySaleStatus::Confirmed->value)
                    ->exists();

                if ($sameDateExists) {
                    throw new InventoryBusinessException('Ngày bán này đã có phiếu được xác nhận.', 409);
                }

                // Revalidate SKU + decrease tồn kho, rollback toàn bộ nếu thiếu
                foreach ($locked->lines as $line) {
                    $sku = ProductSku::query()
                        ->whereKey($line->sku_id)
                        ->first();

                    if (! $sku instanceof ProductSku || $line->quantity_sold === null) {
                        throw new InventoryBusinessException('Không thể xác nhận phiếu còn dòng lỗi.', 422);
                    }

                    $this->balanceService->decrease(
                        sku: $sku,
                        quantity: (int) $line->quantity_sold,
                        type: InventoryTransactionType::Sale,
                        referenceType: 'daily_sale',
                        referenceId: (string) $locked->id,
                        reason: 'Xác nhận phiếu bán ngày ' . $locked->sales_date->toDateString(),
                        createdBy: $confirmedBy,
                    );
                }

                // Cập nhật trạng thái
                $locked->status = DailySaleStatus::Confirmed;
                $locked->confirmed_sales_date = $locked->sales_date;
                $locked->confirmed_at = now();
                $locked->confirmed_by = $confirmedBy;
                $locked->save();

                return $locked->load(['lines' => fn ($query) => $query->orderBy('row_number')]);
            });
        } catch (InsufficientStockException) {
            throw new InventoryBusinessException('Không đủ tồn kho để xác nhận phiếu bán.', 422);
        }
    }
}
