<?php

namespace App\Domain\Enums;

enum InventoryTransactionType: string
{
    case ImportSync = 'IMPORT_SYNC';
    case Sale = 'SALE';
    case SaleReversal = 'SALE_REVERSAL';
    case BorrowOut = 'BORROW_OUT';
    case BorrowReturn = 'BORROW_RETURN';
}
