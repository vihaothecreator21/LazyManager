<?php

namespace App\Http\Resources;

use App\Models\StockCount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StockCount */
final class StockCountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'count_date' => $this->count_date?->toDateString(),
            'status' => $this->status->value,
            'total_lines' => $this->relationLoaded('lines') ? $this->lines->count() : $this->lines_count,
            'counted_lines' => $this->relationLoaded('lines')
                ? $this->lines->whereNotNull('actual_quantity')->count()
                : $this->counted_lines_count,
            'variance_lines' => $this->relationLoaded('lines')
                ? $this->lines->where('variance', '!=', 0)->count()
                : $this->variance_lines_count,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];

        if ($this->relationLoaded('lines')) {
            $data['lines'] = StockCountLineResource::collection($this->lines)->resolve($request);
        }

        return $data;
    }
}
