<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StockCountLine extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'stock_count_id' => 'integer',
            'sku_id' => 'integer',
            'product_id' => 'integer',
            'expected_quantity' => 'integer',
            'actual_quantity' => 'integer',
            'variance' => 'integer',
        ];
    }

    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(StockCount::class);
    }

    public function sku(): BelongsTo
    {
        return $this->belongsTo(ProductSku::class);
    }
}
