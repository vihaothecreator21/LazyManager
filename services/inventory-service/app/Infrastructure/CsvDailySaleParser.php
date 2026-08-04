<?php

namespace App\Infrastructure;

use App\Domain\Exceptions\InventoryBusinessException;

final class CsvDailySaleParser
{
    private const MAX_DATA_ROWS = 5000;

    /**
     * @return list<array{
     *     row_number: int,
     *     raw_sku_code: string,
     *     raw_quantity_sold: string,
     *     sku_code: string,
     *     quantity_sold: int|null,
     *     error_message: string|null
     * }>
     */
    public function parse(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new InventoryBusinessException('Không đọc được file CSV.');
        }

        try {
            $header = fgetcsv($handle);
            $normalizedHeader = is_array($header)
                ? array_map(fn ($value): string => strtolower(trim((string) $value)), $header)
                : null;

            if (is_array($normalizedHeader) && isset($normalizedHeader[0])) {
                $normalizedHeader[0] = preg_replace('/^\xEF\xBB\xBF/', '', $normalizedHeader[0]) ?? $normalizedHeader[0];
            }

            if ($normalizedHeader !== ['sku_code', 'quantity_sold']) {
                throw new InventoryBusinessException('File CSV phải có đúng hai cột sku_code và quantity_sold.');
            }

            $rows = [];
            $rowNumber = 1;
            $dataRowCount = 0;

            while (($columns = fgetcsv($handle)) !== false) {
                $rowNumber++;

                if ($this->isBlankRow($columns)) {
                    continue;
                }

                $dataRowCount++;

                if ($dataRowCount > self::MAX_DATA_ROWS) {
                    throw new InventoryBusinessException('File CSV chỉ được có tối đa 5000 dòng dữ liệu.');
                }

                $rows[] = $this->parseRow($columns, $rowNumber);
            }

            if ($dataRowCount === 0) {
                throw new InventoryBusinessException('File CSV phải có ít nhất một dòng dữ liệu.');
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param list<string|null> $columns
     */
    private function isBlankRow(array $columns): bool
    {
        foreach ($columns as $column) {
            if (trim((string) $column) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string|null> $columns
     *
     * @return array{
     *     row_number: int,
     *     raw_sku_code: string,
     *     raw_quantity_sold: string,
     *     sku_code: string,
     *     quantity_sold: int|null,
     *     error_message: string|null
     * }
     */
    private function parseRow(array $columns, int $rowNumber): array
    {
        $rawSkuCode = (string) ($columns[0] ?? '');
        $rawQuantitySold = (string) ($columns[1] ?? '');
        $skuCode = strtoupper(trim($rawSkuCode));
        $trimmedQuantity = trim($rawQuantitySold);
        $quantitySold = null;
        $error = null;

        if ($skuCode === '') {
            $error = 'Mã SKU là bắt buộc.';
        } elseif (! preg_match('/^\d+$/', $trimmedQuantity)) {
            $error = 'Số lượng bán phải là số nguyên.';
        } elseif ((int) $trimmedQuantity <= 0) {
            $error = 'Số lượng bán phải lớn hơn 0.';
        } else {
            $quantitySold = (int) $trimmedQuantity;
        }

        return [
            'row_number' => $rowNumber,
            'raw_sku_code' => $rawSkuCode,
            'raw_quantity_sold' => $rawQuantitySold,
            'sku_code' => $skuCode,
            'quantity_sold' => $quantitySold,
            'error_message' => $error,
        ];
    }
}
