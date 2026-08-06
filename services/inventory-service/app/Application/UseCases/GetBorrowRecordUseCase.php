<?php

namespace App\Application\UseCases;

use App\Models\BorrowRecord;

final class GetBorrowRecordUseCase
{
    public function execute(BorrowRecord $borrowRecord): BorrowRecord
    {
        return $borrowRecord->load('sku.product');
    }
}
