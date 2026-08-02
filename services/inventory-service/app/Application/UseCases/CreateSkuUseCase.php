<?php

namespace App\Application\UseCases;

use App\Application\DTOs\CreateSkuData;
use App\Domain\Exceptions\InventoryBusinessException;
use App\Models\Product;
use App\Models\ProductSku;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class CreateSkuUseCase
{
    public function execute(Product $product, CreateSkuData $data): ProductSku
    {
        if (! $product->active || $product->trashed()) {
            throw new InventoryBusinessException('Không thể tạo SKU cho sản phẩm không còn hoạt động.');
        }

        if (ProductSku::withTrashed()->where('sku_code', $data->skuCode)->exists()) {
            throw new InventoryBusinessException('Mã SKU đã tồn tại.', 409);
        }

        try {
            return DB::transaction(function () use ($product, $data): ProductSku {
                $sku = $product->skus()->create([
                    'sku_code' => $data->skuCode,
                    'size' => $data->size,
                    'active' => true,
                ]);
                $sku->balance()->create(['quantity' => 0]);

                return $sku->load(['product', 'balance']);
            });
        } catch (UniqueConstraintViolationException) {
            throw new InventoryBusinessException('Mã SKU đã tồn tại.', 409);
        }
    }
}
