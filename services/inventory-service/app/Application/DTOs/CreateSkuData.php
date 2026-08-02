<?php

namespace App\Application\DTOs;

final readonly class CreateSkuData
{
    public string $skuCode;

    public function __construct(
        string $skuCode,
        public string $size,
    ) {
        $this->skuCode = strtoupper(trim($skuCode));
    }
}
