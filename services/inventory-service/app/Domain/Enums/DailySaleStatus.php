<?php

namespace App\Domain\Enums;

enum DailySaleStatus: string
{
    case Draft = 'DRAFT';
    case Confirmed = 'CONFIRMED';
    case Cancelled = 'CANCELLED';
}
