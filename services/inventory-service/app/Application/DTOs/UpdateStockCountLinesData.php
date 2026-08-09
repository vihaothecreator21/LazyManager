<?php

namespace App\Application\DTOs;

final readonly class UpdateStockCountLinesData
{
    /**
     * @param array<int, UpdateStockCountLineItemData> $lines
     */
    public function __construct(public array $lines)
    {
    }
}
