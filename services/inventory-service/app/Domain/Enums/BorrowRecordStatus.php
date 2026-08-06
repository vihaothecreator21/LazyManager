<?php

namespace App\Domain\Enums;

enum BorrowRecordStatus: string
{
    case Borrowed = 'BORROWED';
    case Returned = 'RETURNED';
}
