<?php

namespace App\Application\UseCases;

use App\Application\DTOs\UpdateProductData;
use App\Domain\Exceptions\InventoryBusinessException;
use App\Models\Product;
use Illuminate\Database\UniqueConstraintViolationException;

final class UpdateProductUseCase
{
    public function execute(Product $product, UpdateProductData $data): Product
    {
        if (
            $data->productCode !== null
            && Product::withTrashed()
                ->where('product_code', $data->productCode)
                ->whereKeyNot($product->id)
                ->exists()
        ) {
            throw new InventoryBusinessException('Mã sản phẩm đã tồn tại.', 409);
        }

        if ($data->productCode !== null) {
            $product->product_code = $data->productCode;
        }

        if ($data->name !== null) {
            $product->name = $data->name;
        }

        try {
            $product->save();
        } catch (UniqueConstraintViolationException) {
            throw new InventoryBusinessException('Mã sản phẩm đã tồn tại.', 409);
        }

        return $product->fresh()->load('skus.balance');
    }
}
