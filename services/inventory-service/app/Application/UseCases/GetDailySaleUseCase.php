<?php

namespace App\Application\UseCases;

use App\Models\DailySale;

final class GetDailySaleUseCase
{
    public function execute(DailySale $dailySale): DailySale
    {
        return $dailySale->load(['lines' => fn ($query) => $query->orderBy('row_number')]);
    }
}
