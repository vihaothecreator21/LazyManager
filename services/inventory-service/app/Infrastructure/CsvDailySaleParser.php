<?php

namespace App\Infrastructure;

use App\Domain\Exceptions\InventoryBusinessException;

final class CsvDailySaleParser
{
    private const MAX_DATA_ROWS = 5000;

    /**
     * @return list<array{
     *     row_number: int,
     *     raw_product_name: string|null,
     *     raw_variant: string|null,
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
            $format = is_array($header) ? $this->resolveFormat($header) : null;

            if ($format === null) {
                throw new InventoryBusinessException(
                    'File CSV phải có cột sku_code + quantity_sold hoặc TÊN + MÀU / SIZE + Số Lượng Bán.'
                );
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

                $rows[] = $this->parseRow($columns, $rowNumber, $format);
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
     *     raw_product_name: string|null,
     *     raw_variant: string|null,
     *     raw_sku_code: string,
     *     raw_quantity_sold: string,
     *     sku_code: string,
     *     quantity_sold: int|null,
     *     error_message: string|null
     * }
     */
    private function parseRow(array $columns, int $rowNumber, array $format): array
    {
        $rawProductName = $format['product_name_index'] === null
            ? null
            : (string) ($columns[$format['product_name_index']] ?? '');
        $rawVariant = $format['variant_index'] === null
            ? null
            : (string) ($columns[$format['variant_index']] ?? '');
        $rawSkuCode = $format['sku_index'] === null ? '' : (string) ($columns[$format['sku_index']] ?? '');
        $rawQuantitySold = (string) ($columns[$format['quantity_index']] ?? '');
        $skuCode = strtoupper(trim($rawSkuCode));
        $trimmedQuantity = trim($rawQuantitySold);
        $quantitySold = null;
        $error = null;

        if ($skuCode === '' && trim((string) $rawProductName) === '') {
            $error = 'Mã SKU là bắt buộc.';
        } elseif (! preg_match('/^(?:x\s*)?\d+$/i', $trimmedQuantity)) {
            $error = 'Số lượng bán phải là số nguyên.';
        } elseif ((int) preg_replace('/\D+/', '', $trimmedQuantity) <= 0) {
            $error = 'Số lượng bán phải lớn hơn 0.';
        } else {
            $quantitySold = (int) preg_replace('/\D+/', '', $trimmedQuantity);
        }

        return [
            'row_number' => $rowNumber,
            'raw_product_name' => $rawProductName === null ? null : trim($rawProductName),
            'raw_variant' => $rawVariant === null ? null : trim($rawVariant),
            'raw_sku_code' => $rawSkuCode,
            'raw_quantity_sold' => $rawQuantitySold,
            'sku_code' => $skuCode,
            'quantity_sold' => $quantitySold,
            'error_message' => $error,
        ];
    }

    /**
     * @param list<string|null> $header
     *
     * @return array{sku_index: int|null, product_name_index: int|null, variant_index: int|null, quantity_index: int}|null
     */
    private function resolveFormat(array $header): ?array
    {
        $skuIndex = $this->findHeaderIndex($header, ['sku_code']);
        $quantityIndex = $this->findHeaderIndex($header, ['quantity_sold']);

        if ($skuIndex !== null && $quantityIndex !== null) {
            return [
                'sku_index' => $skuIndex,
                'product_name_index' => null,
                'variant_index' => null,
                'quantity_index' => $quantityIndex,
            ];
        }

        $productNameIndex = $this->findHeaderIndex($header, ['tên', 'ten']);
        $variantIndex = $this->findHeaderIndex($header, ['màu / size', 'mau / size', 'màu/size', 'mau/size']);
        $quantityIndex = $this->findHeaderIndex($header, ['số lượng bán', 'so luong ban']);

        if ($productNameIndex === null || $variantIndex === null || $quantityIndex === null) {
            return null;
        }

        return [
            'sku_index' => null,
            'product_name_index' => $productNameIndex,
            'variant_index' => $variantIndex,
            'quantity_index' => $quantityIndex,
        ];
    }

    /**
     * @param list<string|null> $header
     * @param list<string> $acceptedNames
     */
    private function findHeaderIndex(array $header, array $acceptedNames): ?int
    {
        foreach ($header as $index => $value) {
            if (in_array($this->normalizeHeader((string) $value), $acceptedNames, true)) {
                return $index;
            }
        }

        return null;
    }

    private function normalizeHeader(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', trim($value)) ?? '';
        $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);

        return preg_replace('/\s+/u', ' ', $value) ?? $value;
    }
}
