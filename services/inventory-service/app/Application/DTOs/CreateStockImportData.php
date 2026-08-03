<?php

namespace App\Application\DTOs;

use Illuminate\Http\UploadedFile;

final readonly class CreateStockImportData
{
    public function __construct(
        public UploadedFile $file,
        public ?int $createdBy,
    ) {
    }
}
