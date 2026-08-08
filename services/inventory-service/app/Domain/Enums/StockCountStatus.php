<?php

namespace App\Domain\Enums;

enum StockCountStatus: string
{
    case Draft = 'DRAFT';
    case Counted = 'COUNTED';
}
