<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class InventoryBalanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'sku_id' => $this->id,
            'sku_code' => $this->sku_code,
            'size' => $this->size,
            'sku_active' => $this->active,
            'product_id' => $this->product->id,
            'product_code' => $this->product->product_code,
            'product_name' => $this->product->name,
            'product_active' => $this->product->active,
            'quantity' => $this->balance->quantity,
            'updated_at' => $this->balance->updated_at?->toJSON(),
        ];
    }
}
