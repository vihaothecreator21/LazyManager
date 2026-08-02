<?php

namespace App\Application\UseCases;

use App\Application\DTOs\UpdateSkuData;
use App\Domain\Exceptions\InventoryBusinessException;
use App\Models\ProductSku;
use Illuminate\Database\UniqueConstraintViolationException;

final class UpdateSkuUseCase
{
    public function execute(ProductSku $sku, UpdateSkuData $data): ProductSku
    {
        if (
            $data->skuCode !== null
            && ProductSku::withTrashed()
                ->where('sku_code', $data->skuCode)
                ->whereKeyNot($sku->id)
                ->exists()
        ) {
            throw new InventoryBusinessException('Mã SKU đã tồn tại.', 409);
        }

        if ($data->skuCode !== null) {
            $sku->sku_code = $data->skuCode;
        }

        if ($data->size !== null) {
            $sku->size = $data->size;
        }

        try {
            $sku->save();
        } catch (UniqueConstraintViolationException) {
            throw new InventoryBusinessException('Mã SKU đã tồn tại.', 409);
        }

        return $sku->fresh()->load(['product', 'balance']);
    }
}
