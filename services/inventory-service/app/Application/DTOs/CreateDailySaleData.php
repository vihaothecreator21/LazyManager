<?php

namespace App\Application\DTOs;

use Illuminate\Http\UploadedFile;

final readonly class CreateDailySaleData
{
    public function __construct(
        public UploadedFile $file,
        public string $salesDate,
        public ?int $createdBy,
    ) {
    }
}
