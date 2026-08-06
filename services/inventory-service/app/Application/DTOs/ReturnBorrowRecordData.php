<?php

namespace App\Application\DTOs;

final readonly class ReturnBorrowRecordData
{
    public function __construct(public ?string $returnNote)
    {
    }
}
