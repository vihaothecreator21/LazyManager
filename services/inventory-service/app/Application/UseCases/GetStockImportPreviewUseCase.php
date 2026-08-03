<?php

namespace App\Application\UseCases;

use App\Models\StockImport;

final class GetStockImportPreviewUseCase
{
    public function execute(StockImport $stockImport): StockImport
    {
        return $stockImport->load(['lines' => fn ($query) => $query->orderBy('row_number')]);
    }
}
