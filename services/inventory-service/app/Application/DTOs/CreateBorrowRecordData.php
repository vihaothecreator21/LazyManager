<?php

namespace App\Application\DTOs;

final readonly class CreateBorrowRecordData
{
    public function __construct(
        public int $skuId,
        public int $quantity,
        public string $borrowerName,
        public string $borrowLocation,
        public ?string $note,
    ) {
    }
}
