<?php

namespace App\Models;

use App\Domain\Enums\StockCountStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class StockCount extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'count_date' => 'date',
            'status' => StockCountStatus::class,
            'created_by' => 'integer',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockCountLine::class);
    }
}
