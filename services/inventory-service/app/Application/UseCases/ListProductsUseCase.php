<?php

namespace App\Application\UseCases;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ListProductsUseCase
{
    /**
     * @return Collection<int, Product>
     */
    public function execute(?string $search): Collection
    {
        $query = Product::query()->with('skus.balance')->orderByDesc('id');
        $search = trim((string) $search);

        if ($search !== '') {
            $operator = DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
            $term = '%'.$search.'%';

            $query->where(function (Builder $query) use ($operator, $term): void {
                $query
                    ->where('product_code', $operator, $term)
                    ->orWhere('name', $operator, $term)
                    ->orWhereHas('skus', fn (Builder $skuQuery) => $skuQuery->where('sku_code', $operator, $term));
            });
        }

        return $query->get();
    }
}
