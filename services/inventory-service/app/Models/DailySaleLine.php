<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DailySaleLine extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'row_number' => 'integer',
            'sku_id' => 'integer',
            'quantity_sold' => 'integer',
            'preview_quantity_before' => 'integer',
            'preview_quantity_after' => 'integer',
        ];
    }

    public function dailySale(): BelongsTo
    {
        return $this->belongsTo(DailySale::class);
    }

    public function sku(): BelongsTo
    {
        return $this->belongsTo(ProductSku::class);
    }
}
