<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StockImportLine extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'row_number' => 'integer',
            'sku_id' => 'integer',
            'quantity' => 'integer',
            'quantity_before' => 'integer',
            'quantity_after' => 'integer',
        ];
    }

    public function stockImport(): BelongsTo
    {
        return $this->belongsTo(StockImport::class);
    }

    public function sku(): BelongsTo
    {
        return $this->belongsTo(ProductSku::class);
    }
}
