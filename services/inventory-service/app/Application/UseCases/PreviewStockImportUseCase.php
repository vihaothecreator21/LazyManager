<?php

namespace App\Application\UseCases;

use App\Application\DTOs\CreateStockImportData;
use App\Domain\Enums\StockImportStatus;
use App\Domain\Exceptions\InventoryBusinessException;
use App\Infrastructure\CsvStockImportParser;
use App\Models\ProductSku;
use App\Models\StockImport;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class PreviewStockImportUseCase
{
    public function __construct(private readonly CsvStockImportParser $parser)
    {
    }

    public function execute(CreateStockImportData $data): StockImport
    {
        $path = $data->file->getRealPath();

        if ($path === false) {
            throw new InventoryBusinessException('Không đọc được file CSV.');
        }

        $hash = hash_file('sha256', $path);
        $rows = $this->resolveRows($this->parser->parse($path));

        try {
            return DB::transaction(function () use ($data, $hash, $rows): StockImport {
                $stockImport = StockImport::query()->create([
                    'file_name' => $data->file->getClientOriginalName(),
                    'file_hash' => $hash,
                    'status' => StockImportStatus::Previewed,
                    'created_by' => $data->createdBy,
                ]);

                foreach ($rows as $row) {
                    $stockImport->lines()->create($row);
                }

                return $stockImport->load(['lines' => fn ($query) => $query->orderBy('row_number')]);
            });
        } catch (UniqueConstraintViolationException) {
            throw new InventoryBusinessException('File này đã được import trước đó.', 409);
        }
    }

    /**
     * @param list<array{
     *     row_number: int,
     *     raw_sku_code: string,
     *     raw_quantity: string,
     *     sku_code: string,
     *     quantity: int|null,
     *     error_message: string|null
     * }> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function resolveRows(array $rows): array
    {
        $seenSkuCodes = [];
        $resolved = [];

        foreach ($rows as $row) {
            $error = $row['error_message'];
            $sku = ProductSku::withTrashed()
                ->with('balance')
                ->where('sku_code', $row['sku_code'])
                ->first();

            if ($error === null && isset($seenSkuCodes[$row['sku_code']])) {
                $error = 'SKU bị lặp trong file.';
            } elseif ($error === null && ! $sku instanceof ProductSku) {
                $error = 'Không tìm thấy SKU.';
            } elseif ($error === null && ($sku->trashed() || ! $sku->active)) {
                $error = 'SKU đã ngừng hoạt động.';
            }

            if ($row['sku_code'] !== '') {
                $seenSkuCodes[$row['sku_code']] = true;
            }

            $quantityBefore = null;
            $quantityAfter = null;

            if ($error === null && $sku instanceof ProductSku) {
                if ($sku->balance === null) {
                    throw new InventoryBusinessException('Không tìm thấy dữ liệu tồn kho của SKU.');
                }

                $quantityBefore = $sku->balance->quantity;
                $quantityAfter = $row['quantity'];
            }

            $resolved[] = [
                'row_number' => $row['row_number'],
                'raw_sku_code' => $row['raw_sku_code'],
                'raw_quantity' => $row['raw_quantity'],
                'sku_code' => $row['sku_code'],
                'sku_id' => $error === null && $sku instanceof ProductSku ? $sku->id : null,
                'quantity' => $row['quantity'],
                'quantity_before' => $quantityBefore,
                'quantity_after' => $quantityAfter,
                'error_message' => $error,
            ];
        }

        return $resolved;
    }
}
