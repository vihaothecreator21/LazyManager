<?php

namespace App\Application\UseCases;

use App\Application\DTOs\CreateStockCountData;
use App\Domain\Enums\StockCountStatus;
use App\Domain\Exceptions\InventoryBusinessException;
use App\Models\InventoryBalance;
use App\Models\StockCount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class CreateStockCountUseCase
{
    public function execute(CreateStockCountData $data): StockCount
    {
        return DB::transaction(function () use ($data): StockCount {
            $balances = InventoryBalance::query()
                ->with(['sku.product'])
                ->whereHas('sku', fn (Builder $query) => $query->where('active', true))
                ->whereHas('sku.product', fn (Builder $query) => $query->where('active', true))
                ->lockForUpdate()
                ->orderBy('sku_id')
                ->get();

            if ($balances->isEmpty()) {
                throw new InventoryBusinessException('Phiên kiểm kho chưa có SKU đang hoạt động để kiểm.', 409);
            }

            $stockCount = StockCount::query()->create([
                'name' => $data->name,
                'count_date' => $data->countDate ?? now()->toDateString(),
                'status' => StockCountStatus::Draft->value,
                'created_by' => $data->createdBy,
            ]);

            foreach ($balances as $balance) {
                $sku = $balance->sku;
                $product = $sku->product;

                $stockCount->lines()->create([
                    'sku_id' => $sku->id,
                    'sku_code' => $sku->sku_code,
                    'product_id' => $product->id,
                    'product_code' => $product->product_code,
                    'product_name' => $product->name,
                    'size' => $sku->size,
                    'expected_quantity' => $balance->quantity,
                    'actual_quantity' => null,
                    'variance' => null,
                ]);
            }

            return $stockCount->load('lines');
        });
    }
}
