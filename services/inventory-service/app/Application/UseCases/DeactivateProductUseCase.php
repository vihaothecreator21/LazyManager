<?php

namespace App\Application\UseCases;

use App\Models\Product;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

final class DeactivateProductUseCase
{
    public function execute(Product $product): Product
    {
        return DB::transaction(function () use ($product): Product {
            $product->active = false;
            $product->save();

            $product->skus()->update(['active' => false]);
            $product->skus()->delete();
            $product->delete();

            return Product::withTrashed()
                ->with(['skus' => fn (HasMany $query) => $query->withTrashed()->with('balance')])
                ->findOrFail($product->id);
        });
    }
}
