<?php

namespace App\Application\UseCases;

use App\Application\DTOs\CreateBorrowRecordData;
use App\Domain\Enums\BorrowRecordStatus;
use App\Domain\Enums\InventoryTransactionType;
use App\Domain\Exceptions\InsufficientStockException;
use App\Domain\Exceptions\InventoryBusinessException;
use App\Domain\Services\InventoryBalanceService;
use App\Models\BorrowRecord;
use App\Models\ProductSku;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateBorrowRecordUseCase
{
    public function __construct(private readonly InventoryBalanceService $balanceService)
    {
    }

    public function execute(CreateBorrowRecordData $data, ?int $createdBy): BorrowRecord
    {
        try {
            return DB::transaction(function () use ($data, $createdBy): BorrowRecord {
                $sku = ProductSku::query()
                    ->whereKey($data->skuId)
                    ->lockForUpdate()
                    ->first();

                if (! $sku instanceof ProductSku || ! $sku->active) {
                    throw new InventoryBusinessException('SKU đã ngừng hoạt động và không thể cho mượn.', 409);
                }

                $borrowRecord = BorrowRecord::query()->create([
                    'sku_id' => $sku->id,
                    'quantity' => $data->quantity,
                    'status' => BorrowRecordStatus::Borrowed->value,
                    'borrower_name' => $data->borrowerName,
                    'borrow_location' => $data->borrowLocation,
                    'note' => $data->note,
                    'borrowed_at' => now(),
                    'created_by' => $createdBy,
                ]);

                $this->balanceService->decrease(
                    sku: $sku,
                    quantity: $data->quantity,
                    type: InventoryTransactionType::BorrowOut,
                    referenceType: 'borrow_record',
                    referenceId: (string) $borrowRecord->id,
                    reason: Str::limit('Cho mượn: '.$data->borrowLocation, 255, ''),
                    createdBy: $createdBy,
                );

                return $borrowRecord->load('sku.product');
            });
        } catch (InsufficientStockException) {
            throw new InventoryBusinessException('Không đủ tồn kho để cho mượn sản phẩm.', 422);
        }
    }
}
