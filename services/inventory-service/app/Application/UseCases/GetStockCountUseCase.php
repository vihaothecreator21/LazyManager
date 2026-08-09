<?php

namespace App\Application\UseCases;

use App\Models\StockCount;

final class GetStockCountUseCase
{
    public function execute(StockCount $stockCount): StockCount
    {
        return $stockCount->load('lines');
    }
}
