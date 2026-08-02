<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InventoryBalance extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    public function sku(): BelongsTo
    {
        return $this->belongsTo(ProductSku::class, 'sku_id');
    }
}
