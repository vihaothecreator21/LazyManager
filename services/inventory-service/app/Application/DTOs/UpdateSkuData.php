<?php

namespace App\Application\DTOs;

final readonly class UpdateSkuData
{
    public ?string $skuCode;

    public function __construct(
        ?string $skuCode,
        public ?string $size,
    ) {
        $this->skuCode = $skuCode !== null ? strtoupper(trim($skuCode)) : null;
    }
}
