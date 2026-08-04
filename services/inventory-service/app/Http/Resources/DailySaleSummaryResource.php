<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class DailySaleSummaryResource extends JsonResource
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
            'status' => $this->status->value,
            'has_errors' => (bool) $this->has_errors,
            'lines_count' => $this->lines_count,
            'confirmed_at' => $this->confirmed_at?->toJSON(),
            'cancelled_at' => $this->cancelled_at?->toJSON(),
            'created_at' => $this->created_at?->toJSON(),
        ];
    }
}
