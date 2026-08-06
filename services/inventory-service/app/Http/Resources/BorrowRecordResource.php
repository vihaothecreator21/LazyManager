<?php

namespace App\Http\Resources;

use App\Models\BorrowRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BorrowRecord */
final class BorrowRecordResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'sku_id' => $this->sku_id,
            'sku_code' => $this->sku->sku_code,
            'product_id' => $this->sku->product->id,
            'product_code' => $this->sku->product->product_code,
            'product_name' => $this->sku->product->name,
            'quantity' => $this->quantity,
            'borrower_name' => $this->borrower_name,
            'borrow_location' => $this->borrow_location,
            'note' => $this->note,
            'return_note' => $this->return_note,
            'created_by' => $this->created_by,
            'returned_by' => $this->returned_by,
            'borrowed_at' => $this->borrowed_at?->toJSON(),
            'returned_at' => $this->returned_at?->toJSON(),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
