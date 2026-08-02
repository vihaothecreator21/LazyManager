<?php

namespace App\Models;

use App\Domain\Enums\InventoryTransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InventoryTransaction extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => InventoryTransactionType::class,
            'quantity_change' => 'integer',
            'quantity_before' => 'integer',
            'quantity_after' => 'integer',
            'created_by' => 'integer',
        ];
    }

    public function sku(): BelongsTo
    {
        return $this->belongsTo(ProductSku::class, 'sku_id');
    }
}
