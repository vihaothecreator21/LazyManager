<?php

namespace App\Application\DTOs;

final readonly class CreateProductData
{
    public string $productCode;

    public function __construct(
        string $productCode,
        public string $name,
    ) {
        $this->productCode = strtoupper(trim($productCode));
    }
}
