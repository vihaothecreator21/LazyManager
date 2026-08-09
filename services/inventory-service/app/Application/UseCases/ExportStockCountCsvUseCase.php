<?php

namespace App\Application\UseCases;

use App\Models\StockCount;

final class ExportStockCountCsvUseCase
{
    public function execute(StockCount $stockCount): string
    {
        $handle = fopen('php://temp', 'w+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['sku_code', 'product_name', 'size', 'expected_quantity', 'actual_quantity', 'note']);

        foreach ($stockCount->load('lines')->lines as $line) {
            fputcsv($handle, [
                $line->sku_code,
                $line->product_name,
                $line->size,
                $line->expected_quantity,
                $line->actual_quantity,
                $line->note,
            ]);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return $content;
    }
}
