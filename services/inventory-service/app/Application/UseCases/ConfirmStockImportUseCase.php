<?php

namespace App\Application\UseCases;

use App\Domain\Enums\InventoryTransactionType;
use App\Domain\Enums\StockImportStatus;
use App\Domain\Exceptions\InventoryBusinessException;
use App\Domain\Services\InventoryBalanceService;
use App\Models\Product;
use App\Models\ProductSku;
use App\Models\StockImport;
use App\Models\StockImportLine;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class ConfirmStockImportUseCase
{
    public function __construct(private readonly InventoryBalanceService $balanceService)
    {
    }

    public function execute(StockImport $stockImport, ?int $createdBy): StockImport
    {
        try {
            return DB::transaction(function () use ($stockImport, $createdBy): StockImport {
                $lockedImport = StockImport::query()
                    ->whereKey($stockImport->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedImport->status === StockImportStatus::Confirmed) {
                    throw new InventoryBusinessException('File import này đã được xác nhận.', 409);
                }

                $lockedImport->load(['lines' => fn ($query) => $query->orderBy('row_number')]);

                if ($lockedImport->lines->contains(fn ($line): bool => $line->error_message !== null)) {
                    throw new InventoryBusinessException('Không thể xác nhận file còn dòng lỗi.');
                }

                foreach ($lockedImport->lines as $line) {
                    $sku = ProductSku::query()->whereKey($line->sku_id)->first();

                    if (! $sku instanceof ProductSku && $line->quantity !== null) {
                        $sku = $this->createSkuFromImportLine($line, $lockedImport->lines);
                        $line->sku_id = $sku->id;
                        $line->save();
                    }

                    if (! $sku instanceof ProductSku || $line->quantity === null) {
                        throw new InventoryBusinessException('Không thể xác nhận file còn dòng lỗi.');
                    }

                    $this->balanceService->synchronize(
                        sku: $sku,
                        targetQuantity: $line->quantity,
                        type: InventoryTransactionType::ImportSync,
                        referenceType: 'stock_import',
                        referenceId: (string) $lockedImport->id,
                        reason: 'Đồng bộ tồn kho từ file CSV.',
                        createdBy: $createdBy,
                    );
                }

                $lockedImport->status = StockImportStatus::Confirmed;
                $lockedImport->confirmed_at = now();
                $lockedImport->save();

                return $lockedImport->load(['lines' => fn ($query) => $query->orderBy('row_number')]);
            });
        } catch (QueryException $exception) {
            if (in_array($exception->getCode(), ['23000', '23505'], true)) {
                throw new InventoryBusinessException('Sản phẩm hoặc SKU đã tồn tại. Vui lòng tải lại preview.', 409);
            }

            throw $exception;
        }
    }

    private function createSkuFromImportLine(StockImportLine $line, iterable $importLines): ProductSku
    {
        $productName = trim((string) $line->raw_product_name);

        if ($productName === '') {
            throw new InventoryBusinessException('Không tìm thấy SKU.');
        }

        $productCode = $this->productCodeFromImportLine($line, $importLines);
        $product = Product::withTrashed()
            ->where('product_code', $productCode)
            ->lockForUpdate()
            ->first();

        if ($product instanceof Product && ($product->trashed() || ! $product->active)) {
            throw new InventoryBusinessException('Sản phẩm đã ngừng hoạt động.');
        }

        if (! $product instanceof Product) {
            $product = Product::query()->create([
                'product_code' => $productCode,
                'name' => $productName,
                'active' => true,
            ]);
        }

        $sku = ProductSku::withTrashed()
            ->where('sku_code', $line->sku_code)
            ->lockForUpdate()
            ->first();

        if ($sku instanceof ProductSku && ($sku->trashed() || ! $sku->active)) {
            throw new InventoryBusinessException('SKU đã ngừng hoạt động.');
        }

        if ($sku instanceof ProductSku) {
            return $sku;
        }

        $sku = $product->skus()->create([
            'sku_code' => $line->sku_code,
            'size' => $this->sizeFromImportLine($line),
            'active' => true,
        ]);
        $sku->balance()->create(['quantity' => 0]);

        return $sku;
    }

    private function sizeFromImportLine(StockImportLine $line): string
    {
        $variant = trim((string) $line->raw_variant);

        if ($variant !== '') {
            return $variant;
        }

        foreach (['XXXL', 'XXL', 'XL', 'XS', 'S', 'M', 'L'] as $suffix) {
            if (str_ends_with($line->sku_code, $suffix)) {
                return $suffix;
            }
        }

        return $line->sku_code;
    }

    private function productCodeFromImportLine(StockImportLine $line, iterable $importLines): string
    {
        $size = $this->sizeSuffixFromVariant($line);

        if ($size !== null && str_ends_with($line->sku_code, $size)) {
            $candidate = substr($line->sku_code, 0, -strlen($size));

            foreach ($importLines as $sibling) {
                if (! $sibling instanceof StockImportLine || $sibling->id === $line->id) {
                    continue;
                }

                if (
                    trim((string) $sibling->raw_product_name) === trim((string) $line->raw_product_name)
                    && str_starts_with($sibling->sku_code, $candidate)
                ) {
                    return $candidate;
                }
            }
        }

        return $line->sku_code;
    }

    private function sizeSuffixFromVariant(StockImportLine $line): ?string
    {
        $variant = trim((string) $line->raw_variant);

        if ($variant === '') {
            return null;
        }

        foreach (['XXXL', 'XXL', 'XL', 'XS', 'S', 'M', 'L'] as $suffix) {
            if (preg_match('/(?:^|\/|\s)'.preg_quote($suffix, '/').'$/u', $variant) === 1) {
                return $suffix;
            }
        }

        return null;
    }
}
