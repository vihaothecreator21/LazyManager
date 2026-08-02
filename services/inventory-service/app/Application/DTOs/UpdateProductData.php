<?php

namespace App\Application\DTOs;

final readonly class UpdateProductData
{
    public ?string $productCode;

    public function __construct(
        ?string $productCode,
        public ?string $name,
    ) {
        $this->productCode = $productCode !== null ? strtoupper(trim($productCode)) : null;
    }
}
