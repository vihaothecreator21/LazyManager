<?php

namespace App\Application\UseCases;

use App\Models\ProductSku;

final class DeactivateSkuUseCase
{
    public function execute(ProductSku $sku): ProductSku
    {
        $sku->active = false;
        $sku->save();
        $sku->delete();

        return ProductSku::withTrashed()
            ->with(['product', 'balance'])
            ->findOrFail($sku->id);
    }
}
