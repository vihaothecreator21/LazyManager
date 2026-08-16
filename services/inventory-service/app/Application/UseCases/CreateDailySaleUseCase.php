<?php

namespace App\Application\UseCases;

use App\Application\DTOs\CreateDailySaleData;
use App\Domain\Enums\DailySaleStatus;
use App\Domain\Exceptions\InventoryBusinessException;
use App\Infrastructure\CsvDailySaleParser;
use App\Models\DailySale;
use App\Models\Product;
use App\Models\ProductSku;
use Illuminate\Support\Facades\DB;

final class CreateDailySaleUseCase
{
    public function __construct(private readonly CsvDailySaleParser $parser)
    {
    }

    public function execute(CreateDailySaleData $data): DailySale
    {
        $path = $data->file->getRealPath();

        if ($path === false) {
            throw new InventoryBusinessException('Không đọc được file CSV.');
        }

        if (DailySale::query()
            ->whereDate('sales_date', $data->salesDate)
            ->where('status', DailySaleStatus::Confirmed->value)
            ->exists()) {
            throw new InventoryBusinessException('Ngày bán này đã có phiếu được xác nhận.', 409);
        }

        $rows = $this->resolveRows($this->parser->parse($path));
        $hash = hash_file('sha256', $path);

        return DB::transaction(function () use ($data, $hash, $rows): DailySale {
            $dailySale = DailySale::query()->create([
                'sales_date' => $data->salesDate,
                'file_name' => $data->file->getClientOriginalName(),
                'file_hash' => $hash,
                'status' => DailySaleStatus::Draft,
                'created_by' => $data->createdBy,
            ]);

            foreach ($rows as $row) {
                $dailySale->lines()->create($row);
            }

            return $dailySale->load(['lines' => fn ($query) => $query->orderBy('row_number')]);
        });
    }

    /**
     * @param list<array{
     *     row_number: int,
     *     raw_product_name: string|null,
     *     raw_variant: string|null,
     *     raw_sku_code: string,
     *     raw_quantity_sold: string,
     *     sku_code: string,
     *     quantity_sold: int|null,
     *     error_message: string|null
     * }> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function resolveRows(array $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $resolved = $this->resolveRow($row);

            if ($resolved['error_message'] !== null || $resolved['sku_id'] === null) {
                $grouped[] = $resolved;
                continue;
            }

            $groupKey = 'sku-'.$resolved['sku_id'];

            if (! isset($grouped[$groupKey])) {
                $grouped[$groupKey] = $resolved;
                continue;
            }

            $grouped[$groupKey]['quantity_sold'] += $resolved['quantity_sold'] ?? 0;
            $grouped[$groupKey]['preview_quantity_after'] =
                $grouped[$groupKey]['preview_quantity_before'] - $grouped[$groupKey]['quantity_sold'];
        }

        return array_values($grouped);
    }

    /**
     * @param array{
     *     row_number: int,
     *     raw_product_name: string|null,
     *     raw_variant: string|null,
     *     raw_sku_code: string,
     *     raw_quantity_sold: string,
     *     sku_code: string,
     *     quantity_sold: int|null,
     *     error_message: string|null
     * } $row
     *
     * @return array<string, mixed>
     */
    private function resolveRow(array $row): array
    {
        $error = $row['error_message'];
        $sku = $this->findSkuForRow($row);

        if ($error === null && ! $sku instanceof ProductSku) {
            $error = 'Không tìm thấy SKU.';
        } elseif ($error === null && $sku->product instanceof Product && ($sku->product->trashed() || ! $sku->product->active)) {
            $error = 'Sản phẩm đã ngừng hoạt động.';
        } elseif ($error === null && ($sku->trashed() || ! $sku->active)) {
            $error = 'SKU đã ngừng hoạt động.';
        }

        if ($error === null && $sku instanceof ProductSku && $sku->balance === null) {
            throw new InventoryBusinessException('Không tìm thấy dữ liệu tồn kho của SKU.');
        }

        $quantityBefore = null;
        $quantityAfter = null;

        if ($error === null && $sku instanceof ProductSku) {
            $quantityBefore = $sku->balance->quantity;
            $quantityAfter = $quantityBefore - (int) $row['quantity_sold'];
        }

        return [
            'row_number' => $row['row_number'],
            'raw_product_name' => $row['raw_product_name'],
            'raw_variant' => $row['raw_variant'],
            'raw_sku_code' => $row['raw_sku_code'],
            'raw_quantity_sold' => $row['raw_quantity_sold'],
            'sku_code' => $sku instanceof ProductSku ? $sku->sku_code : $row['sku_code'],
            'sku_id' => $error === null && $sku instanceof ProductSku ? $sku->id : null,
            'quantity_sold' => $row['quantity_sold'],
            'preview_quantity_before' => $quantityBefore,
            'preview_quantity_after' => $quantityAfter,
            'error_message' => $error,
        ];
    }

    /**
     * @param array{raw_product_name: string|null, raw_variant: string|null, sku_code: string} $row
     */
    private function findSkuForRow(array $row): ?ProductSku
    {
        if ($row['sku_code'] !== '') {
            return ProductSku::withTrashed()
                ->with('balance')
                ->where('sku_code', $row['sku_code'])
                ->first();
        }

        $productName = $this->normalizeText((string) $row['raw_product_name']);
        $variant = $this->normalizeText((string) $row['raw_variant']);

        if ($productName === '' || $variant === '') {
            return null;
        }

        $matches = ProductSku::withTrashed()
            ->with(['balance', 'product' => fn ($query) => $query->withTrashed()])
            ->get()
            ->filter(function (ProductSku $sku) use ($productName, $variant): bool {
                if (! $sku->product instanceof Product) {
                    return false;
                }

                return $this->normalizeText($sku->product->name) === $productName
                    && $this->normalizeText($sku->size) === $variant;
            })
            ->values();

        if ($matches->count() !== 1) {
            return null;
        }

        return $matches->first();
    }

    private function normalizeText(string $value): string
    {
        $value = trim($value);
        $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);

        return preg_replace('/\s+/u', ' ', $value) ?? $value;
    }
}
