<?php

namespace App\Application\UseCases;

use App\Models\ProductSku;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ListInventoryBalancesUseCase
{
    /**
     * @return Collection<int, ProductSku>
     */
    public function execute(?string $search): Collection
    {
        $query = ProductSku::query()
            ->with(['product', 'balance'])
            ->orderByDesc('product_skus.id');

        $search = trim((string) $search);

        if ($search !== '') {
            $operator = DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
            $term = '%'.$search.'%';

            $query->where(function (Builder $query) use ($operator, $term): void {
                $query
                    ->where('sku_code', $operator, $term)
                    ->orWhereHas('product', function (Builder $productQuery) use ($operator, $term): void {
                        $productQuery
                            ->where('product_code', $operator, $term)
                            ->orWhere('name', $operator, $term);
                    });
            });
        }

        return $query->get();
    }
}
