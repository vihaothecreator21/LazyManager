<?php

namespace App\Application\UseCases;

use App\Application\DTOs\CreateDailySaleData;
use App\Domain\Enums\DailySaleStatus;
use App\Domain\Exceptions\InventoryBusinessException;
use App\Infrastructure\CsvDailySaleParser;
use App\Models\DailySale;
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
            if ($row['error_message'] !== null || $row['sku_code'] === '') {
                $grouped[] = $this->resolveRow($row);
                continue;
            }

            if (! isset($grouped[$row['sku_code']])) {
                $grouped[$row['sku_code']] = $row;
                continue;
            }

            $grouped[$row['sku_code']]['quantity_sold'] += $row['quantity_sold'] ?? 0;
        }

        return array_values(array_map(fn (array $row): array => $this->resolveRow($row), $grouped));
    }

    /**
     * @param array{
     *     row_number: int,
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
        $sku = ProductSku::withTrashed()
            ->with('balance')
            ->where('sku_code', $row['sku_code'])
            ->first();

        if ($error === null && ! $sku instanceof ProductSku) {
            $error = 'Không tìm thấy SKU.';
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
            'raw_sku_code' => $row['raw_sku_code'],
            'raw_quantity_sold' => $row['raw_quantity_sold'],
            'sku_code' => $row['sku_code'],
            'sku_id' => $error === null && $sku instanceof ProductSku ? $sku->id : null,
            'quantity_sold' => $row['quantity_sold'],
            'preview_quantity_before' => $quantityBefore,
            'preview_quantity_after' => $quantityAfter,
            'error_message' => $error,
        ];
    }
}
