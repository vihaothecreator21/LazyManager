<?php

namespace App\Application\UseCases;

use App\Models\StockCount;
use Illuminate\Support\Collection;

final class ListStockCountsUseCase
{
    public function execute(): Collection
    {
        return StockCount::query()
            ->withCount([
                'lines',
                'lines as counted_lines_count' => fn ($query) => $query->whereNotNull('actual_quantity'),
                'lines as variance_lines_count' => fn ($query) => $query->where('variance', '!=', 0),
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }
}
