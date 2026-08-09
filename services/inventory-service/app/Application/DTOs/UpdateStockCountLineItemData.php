<?php

namespace App\Application\DTOs;

final readonly class UpdateStockCountLineItemData
{
    public function __construct(
        public int $lineId,
        public int $actualQuantity,
        public ?string $note,
    ) {
    }
}
