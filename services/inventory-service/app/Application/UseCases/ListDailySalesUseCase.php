<?php

namespace App\Application\UseCases;

use App\Domain\Exceptions\InventoryBusinessException;
use App\Models\DailySale;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListDailySalesUseCase
{
    public function execute(
        ?string $dateFrom,
        ?string $dateTo,
        ?string $status,
        int $perPage = 20,
    ): LengthAwarePaginator {
        if ($dateFrom !== null && $dateTo !== null && $dateTo < $dateFrom) {
            throw new InventoryBusinessException('Khoảng ngày lọc không hợp lệ.');
        }

        $perPage = max(1, min($perPage, 100));

        return DailySale::query()
            ->withCount('lines')
            ->withExists(['lines as has_errors' => fn ($query) => $query->whereNotNull('error_message')])
            ->when($dateFrom !== null, fn ($query) => $query->whereDate('sales_date', '>=', $dateFrom))
            ->when($dateTo !== null, fn ($query) => $query->whereDate('sales_date', '<=', $dateTo))
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->orderByDesc('sales_date')
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
