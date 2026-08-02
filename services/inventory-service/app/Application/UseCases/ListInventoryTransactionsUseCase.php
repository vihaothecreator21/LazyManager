<?php

namespace App\Application\UseCases;

use App\Models\InventoryTransaction;
use App\Models\ProductSku;
use Illuminate\Support\Collection;

final class ListInventoryTransactionsUseCase
{
    /**
     * @return Collection<int, InventoryTransaction>
     */
    public function execute(ProductSku $sku): Collection
    {
        return $sku->transactions()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }
}
