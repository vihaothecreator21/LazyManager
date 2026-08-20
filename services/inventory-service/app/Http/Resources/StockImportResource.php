<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class StockImportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'file_name' => $this->file_name,
            'file_hash' => $this->file_hash,
            'status' => $this->status->value,
            'has_errors' => $this->lines->contains(fn ($line): bool => $line->error_message !== null),
            'confirmed_at' => $this->confirmed_at?->toJSON(),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toJSON(),
            'lines' => StockImportLineResource::collection($this->whenLoaded('lines'))->resolve($request),
        ];
    }
}
