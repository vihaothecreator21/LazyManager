<?php

namespace App\Application\UseCases;

use App\Models\Product;

final class GetProductUseCase
{
    public function execute(Product $product): Product
    {
        return $product->load('skus.balance');
    }
}
