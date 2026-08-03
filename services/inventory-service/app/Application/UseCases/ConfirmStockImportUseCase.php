<?php

namespace App\Application\UseCases;

use App\Domain\Enums\InventoryTransactionType;
use App\Domain\Enums\StockImportStatus;
use App\Domain\Exceptions\InventoryBusinessException;
use App\Domain\Services\InventoryBalanceService;
use App\Models\ProductSku;
use App\Models\StockImport;
use Illuminate\Support\Facades\DB;

final class ConfirmStockImportUseCase
{
    public function __construct(private readonly InventoryBalanceService $balanceService)
    {
    }

    public function execute(StockImport $stockImport, ?int $createdBy): StockImport
    {
        return DB::transaction(function () use ($stockImport, $createdBy): StockImport {
            $lockedImport = StockImport::query()
                ->whereKey($stockImport->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedImport->status === StockImportStatus::Confirmed) {
                throw new InventoryBusinessException('File import này đã được xác nhận.', 409);
            }

            $lockedImport->load(['lines' => fn ($query) => $query->orderBy('row_number')]);

            if ($lockedImport->lines->contains(fn ($line): bool => $line->error_message !== null)) {
                throw new InventoryBusinessException('Không thể xác nhận file còn dòng lỗi.');
            }

            foreach ($lockedImport->lines as $line) {
                $sku = ProductSku::query()->whereKey($line->sku_id)->first();

                if (! $sku instanceof ProductSku || $line->quantity === null) {
                    throw new InventoryBusinessException('Không thể xác nhận file còn dòng lỗi.');
                }

                $this->balanceService->synchronize(
                    sku: $sku,
                    targetQuantity: $line->quantity,
                    type: InventoryTransactionType::ImportSync,
                    referenceType: 'stock_import',
                    referenceId: (string) $lockedImport->id,
                    reason: 'Đồng bộ tồn kho từ file CSV.',
                    createdBy: $createdBy,
                );
            }

            $lockedImport->status = StockImportStatus::Confirmed;
            $lockedImport->confirmed_at = now();
            $lockedImport->save();

            return $lockedImport->load(['lines' => fn ($query) => $query->orderBy('row_number')]);
        });
    }
}
