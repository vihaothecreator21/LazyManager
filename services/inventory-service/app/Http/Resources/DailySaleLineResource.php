<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class DailySaleLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'row_number' => $this->row_number,
            'raw_product_name' => $this->raw_product_name,
            'raw_variant' => $this->raw_variant,
            'raw_sku_code' => $this->raw_sku_code,
            'raw_quantity_sold' => $this->raw_quantity_sold,
            'sku_code' => $this->sku_code,
            'sku_id' => $this->sku_id,
            'quantity_sold' => $this->quantity_sold,
            'preview_quantity_before' => $this->preview_quantity_before,
            'preview_quantity_after' => $this->preview_quantity_after,
            'error_message' => $this->error_message,
        ];
    }
}
