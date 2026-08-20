<?php

namespace App\Models;

use App\Domain\Enums\DailySaleStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class DailySale extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'sales_date' => 'date',
            'confirmed_sales_date' => 'date',
            'status' => DailySaleStatus::class,
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'created_by' => 'integer',
            'confirmed_by' => 'integer',
            'cancelled_by' => 'integer',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(DailySaleLine::class);
    }
}
