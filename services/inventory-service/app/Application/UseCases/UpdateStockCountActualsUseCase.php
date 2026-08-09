<?php

namespace App\Application\UseCases;

use App\Application\DTOs\UpdateStockCountLinesData;
use App\Domain\Enums\StockCountStatus;
use App\Domain\Exceptions\InventoryBusinessException;
use App\Models\StockCount;
use Illuminate\Support\Facades\DB;

final class UpdateStockCountActualsUseCase
{
    public function execute(StockCount $stockCount, UpdateStockCountLinesData $data): StockCount
    {
        return DB::transaction(function () use ($stockCount, $data): StockCount {
            $lines = $stockCount->lines()->lockForUpdate()->get();
            $linesById = $lines->keyBy('id');

            foreach ($data->lines as $item) {
                $line = $linesById->get($item->lineId);

                if ($line === null) {
                    throw new InventoryBusinessException('Dòng kiểm kho không thuộc phiên hiện tại.', 409);
                }

                $line->update([
                    'actual_quantity' => $item->actualQuantity,
                    'variance' => $item->actualQuantity - $line->expected_quantity,
                    'note' => $item->note,
                ]);
            }

            $allCounted = $lines->every(fn ($line): bool => $line->actual_quantity !== null);

            $stockCount->update([
                'status' => $allCounted ? StockCountStatus::Counted->value : StockCountStatus::Draft->value,
            ]);

            return $stockCount->load('lines');
        });
    }
}
