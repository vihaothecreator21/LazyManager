<?php

namespace App\Application\UseCases;

use App\Application\DTOs\CreateProductData;
use App\Domain\Exceptions\InventoryBusinessException;
use App\Models\Product;
use Illuminate\Database\UniqueConstraintViolationException;

final class CreateProductUseCase
{
    public function execute(CreateProductData $data): Product
    {
        if (Product::withTrashed()->where('product_code', $data->productCode)->exists()) {
            throw new InventoryBusinessException('Mã sản phẩm đã tồn tại.', 409);
        }

        try {
            return Product::query()->create([
                'product_code' => $data->productCode,
                'name' => $data->name,
                'active' => true,
            ])->load('skus.balance');
        } catch (UniqueConstraintViolationException) {
            throw new InventoryBusinessException('Mã sản phẩm đã tồn tại.', 409);
        }
    }
}
