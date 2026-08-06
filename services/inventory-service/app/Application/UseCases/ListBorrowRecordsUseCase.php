<?php

namespace App\Application\UseCases;

use App\Domain\Enums\BorrowRecordStatus;
use App\Domain\Exceptions\InventoryBusinessException;
use App\Models\BorrowRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ListBorrowRecordsUseCase
{
    public function execute(?string $status, ?string $search): Collection
    {
        $query = BorrowRecord::query()->with('sku.product');

        if ($status !== null && $status !== '') {
            $normalizedStatus = strtoupper(trim($status));
            if (! in_array($normalizedStatus, array_column(BorrowRecordStatus::cases(), 'value'), true)) {
                throw new InventoryBusinessException('Trạng thái phiếu mượn không hợp lệ.', 422);
            }

            $query->where('status', $normalizedStatus);
        }

        if ($search !== null && trim($search) !== '') {
            $operator = DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
            $term = '%'.trim($search).'%';

            $query->where(function (Builder $inner) use ($operator, $term): void {
                $inner->where('borrower_name', $operator, $term)
                    ->orWhere('borrow_location', $operator, $term)
                    ->orWhereHas('sku', fn (Builder $skuQuery) => $skuQuery->where('sku_code', $operator, $term))
                    ->orWhereHas('sku.product', fn (Builder $productQuery) => $productQuery
                        ->where('product_code', $operator, $term)
                        ->orWhere('name', $operator, $term));
            });
        }

        return $query
            ->orderByDesc('borrowed_at')
            ->orderByDesc('id')
            ->get();
    }
}
