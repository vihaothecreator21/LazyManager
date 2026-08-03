<?php

namespace App\Infrastructure;

use App\Domain\Exceptions\InventoryBusinessException;

final class CsvStockImportParser
{
    /**
     * @return list<array{
     *     row_number: int,
     *     raw_sku_code: string,
     *     raw_quantity: string,
     *     sku_code: string,
     *     quantity: int|null,
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

            if ($normalizedHeader !== ['sku_code', 'quantity']) {
                throw new InventoryBusinessException('File CSV phải có đúng hai cột sku_code và quantity.');
            }

            $rows = [];
            $rowNumber = 1;

            while (($columns = fgetcsv($handle)) !== false) {
                $rowNumber++;

                if ($columns === [null] || trim(implode('', array_map('strval', $columns))) === '') {
                    continue;
                }

                $rows[] = $this->parseRow($columns, $rowNumber);
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param list<string|null> $columns
     *
     * @return array{
     *     row_number: int,
     *     raw_sku_code: string,
     *     raw_quantity: string,
     *     sku_code: string,
     *     quantity: int|null,
     *     error_message: string|null
     * }
     */
    private function parseRow(array $columns, int $rowNumber): array
    {
        $rawSkuCode = (string) ($columns[0] ?? '');
        $rawQuantity = (string) ($columns[1] ?? '');
        $skuCode = strtoupper(trim($rawSkuCode));
        $trimmedQuantity = trim($rawQuantity);
        $quantity = null;
        $error = null;

        if ($skuCode === '') {
            $error = 'Mã SKU là bắt buộc.';
        } elseif (str_starts_with($trimmedQuantity, '-')) {
            $error = 'Số lượng không được âm.';
        } elseif (! preg_match('/^\d+$/', $trimmedQuantity)) {
            $error = 'Số lượng phải là số nguyên.';
        } else {
            $quantity = (int) $trimmedQuantity;
        }

        return [
            'row_number' => $rowNumber,
            'raw_sku_code' => $rawSkuCode,
            'raw_quantity' => $rawQuantity,
            'sku_code' => $skuCode,
            'quantity' => $quantity,
            'error_message' => $error,
        ];
    }
}
