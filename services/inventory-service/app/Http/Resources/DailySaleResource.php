<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class DailySaleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sales_date' => $this->sales_date?->toDateString(),
            'file_name' => $this->file_name,
            'file_hash' => $this->file_hash,
            'status' => $this->status->value,
            'has_errors' => $this->lines->contains(fn ($line): bool => $line->error_message !== null),
            'confirmed_at' => $this->confirmed_at?->toJSON(),
            'cancelled_at' => $this->cancelled_at?->toJSON(),
            'cancel_reason' => $this->cancel_reason,
            'created_by' => $this->created_by,
            'confirmed_by' => $this->confirmed_by,
            'cancelled_by' => $this->cancelled_by,
            'created_at' => $this->created_at?->toJSON(),
            'lines' => DailySaleLineResource::collection($this->whenLoaded('lines'))->resolve($request),
        ];
    }
}
