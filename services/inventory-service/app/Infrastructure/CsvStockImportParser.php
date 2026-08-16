<?php

namespace App\Infrastructure;

use App\Domain\Exceptions\InventoryBusinessException;

final class CsvStockImportParser
{
    /**
     * @return list<array{
     *     row_number: int,
     *     raw_product_name: string|null,
     *     raw_variant: string|null,
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
            $format = is_array($header) ? $this->resolveFormat($header, $handle) : null;

            if ($format === null) {
                throw new InventoryBusinessException(
                    'File CSV phải có cột sku_code + quantity hoặc SKU + Tồn kho.'
                );
            }

            $rows = [];
            $rowNumber = $format['data_starts_after_row'];

            while (($columns = fgetcsv($handle)) !== false) {
                $rowNumber++;

                if ($columns === [null] || trim(implode('', array_map('strval', $columns))) === '') {
                    continue;
                }

                $rows[] = $this->parseRow(
                    $columns,
                    $rowNumber,
                    $format['sku_index'],
                    $format['quantity_index'],
                    $format['product_name_index'],
                    $format['variant_index'],
                );
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
     *     raw_product_name: string|null,
     *     raw_variant: string|null,
     *     raw_sku_code: string,
     *     raw_quantity: string,
     *     sku_code: string,
     *     quantity: int|null,
     *     error_message: string|null
     * }
     */
    private function parseRow(
        array $columns,
        int $rowNumber,
        int $skuIndex,
        int $quantityIndex,
        ?int $productNameIndex = null,
        ?int $variantIndex = null,
    ): array
    {
        $rawProductName = $productNameIndex === null ? null : (string) ($columns[$productNameIndex] ?? '');
        $rawVariant = $variantIndex === null ? null : (string) ($columns[$variantIndex] ?? '');
        $rawSkuCode = (string) ($columns[$skuIndex] ?? '');
        $rawQuantity = (string) ($columns[$quantityIndex] ?? '');
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
            'raw_product_name' => $rawProductName === null ? null : trim($rawProductName),
            'raw_variant' => $rawVariant === null ? null : trim($rawVariant),
            'raw_sku_code' => $rawSkuCode,
            'raw_quantity' => $rawQuantity,
            'sku_code' => $skuCode,
            'quantity' => $quantity,
            'error_message' => $error,
        ];
    }

    /**
     * @param list<string|null> $header
     *
     * @return array{sku_index: int, quantity_index: int, product_name_index: int|null, variant_index: int|null, data_starts_after_row: int}|null
     */
    private function resolveFormat(array $header, mixed $handle): ?array
    {
        $singleRowFormat = $this->resolveHeaderIndexes($header);

        if ($singleRowFormat !== null) {
            return [...$singleRowFormat, 'data_starts_after_row' => 1];
        }

        $secondHeader = fgetcsv($handle);

        if (! is_array($secondHeader)) {
            return null;
        }

        $skuIndex = $this->findHeaderIndex($header, ['mã sku', 'ma sku', 'sku']);
        $quantityIndex = $this->findHeaderIndex($secondHeader, ['tồn kho', 'ton kho']);

        if ($skuIndex === null || $quantityIndex === null) {
            return null;
        }

        return [
            'sku_index' => $skuIndex,
            'quantity_index' => $quantityIndex,
            'product_name_index' => $this->findHeaderIndex($header, [
                'tên phiên bản',
                'ten phien ban',
                'tên sản phẩm',
                'ten san pham',
            ]),
            'variant_index' => null,
            'data_starts_after_row' => 2,
        ];
    }

    /**
     * @param list<string|null> $header
     *
     * @return array{sku_index: int, quantity_index: int, product_name_index: int|null, variant_index: int|null}|null
     */
    private function resolveHeaderIndexes(array $header): ?array
    {
        $skuIndex = $this->findHeaderIndex($header, ['sku_code', 'mã sku', 'ma sku', 'sku']);
        $quantityIndex = $this->findHeaderIndex($header, ['quantity', 'tồn kho', 'ton kho']);

        if ($skuIndex === null || $quantityIndex === null) {
            return null;
        }

        return [
            'sku_index' => $skuIndex,
            'quantity_index' => $quantityIndex,
            'product_name_index' => $this->findHeaderIndex($header, [
                'tên sản phẩm',
                'ten san pham',
                'tên phiên bản',
                'ten phien ban',
            ]),
            'variant_index' => $this->findHeaderIndex($header, [
                'biến thể',
                'bien the',
                'variant',
            ]),
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
