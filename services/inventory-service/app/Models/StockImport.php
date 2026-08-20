<?php

namespace App\Models;

use App\Domain\Enums\StockImportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class StockImport extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => StockImportStatus::class,
            'confirmed_at' => 'datetime',
            'created_by' => 'integer',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockImportLine::class);
    }
}
