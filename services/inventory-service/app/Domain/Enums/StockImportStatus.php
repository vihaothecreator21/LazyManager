<?php

namespace App\Domain\Enums;

enum StockImportStatus: string
{
    case Previewed = 'PREVIEWED';
    case Confirmed = 'CONFIRMED';
}
