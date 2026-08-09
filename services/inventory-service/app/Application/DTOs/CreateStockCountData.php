<?php

namespace App\Application\DTOs;

final readonly class CreateStockCountData
{
    public function __construct(
        public ?string $name,
        public ?string $countDate,
        public ?int $createdBy,
    ) {
    }
}
