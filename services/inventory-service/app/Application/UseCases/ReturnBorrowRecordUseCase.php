<?php

namespace App\Application\UseCases;

use App\Application\DTOs\ReturnBorrowRecordData;
use App\Domain\Enums\BorrowRecordStatus;
use App\Domain\Enums\InventoryTransactionType;
use App\Domain\Exceptions\InventoryBusinessException;
use App\Domain\Services\InventoryBalanceService;
use App\Models\BorrowRecord;
use App\Models\ProductSku;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ReturnBorrowRecordUseCase
{
    public function __construct(private readonly InventoryBalanceService $balanceService)
    {
    }

    public function execute(BorrowRecord $borrowRecord, ReturnBorrowRecordData $data, ?int $returnedBy): BorrowRecord
    {
        return DB::transaction(function () use ($borrowRecord, $data, $returnedBy): BorrowRecord {
            $locked = BorrowRecord::query()
                ->whereKey($borrowRecord->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === BorrowRecordStatus::Returned) {
                throw new InventoryBusinessException('Phiếu mượn này đã được trả.', 409);
            }

            // Cho phép trả hàng kể cả khi SKU đã bị inactive hoặc soft-deleted.
            $sku = ProductSku::withTrashed()
                ->whereKey($locked->sku_id)
                ->lockForUpdate()
                ->firstOrFail();

            $reason = $data->returnNote !== null && $data->returnNote !== ''
                ? Str::limit('Trả hàng mượn: '.$data->returnNote, 255, '')
                : 'Trả hàng mượn.';

            $this->balanceService->increase(
                sku: $sku,
                quantity: $locked->quantity,
                type: InventoryTransactionType::BorrowReturn,
                referenceType: 'borrow_record',
                referenceId: (string) $locked->id,
                reason: $reason,
                createdBy: $returnedBy,
            );

            $locked->status = BorrowRecordStatus::Returned;
            $locked->returned_at = now();
            $locked->returned_by = $returnedBy;
            $locked->return_note = $data->returnNote;
            $locked->save();

            return $locked->load('sku.product');
        });
    }
}
