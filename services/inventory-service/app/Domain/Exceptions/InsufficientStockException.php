<?php

namespace App\Domain\Exceptions;

final class InsufficientStockException extends InventoryBusinessException
{
    public function __construct()
    {
        parent::__construct('Tồn kho không đủ để thực hiện thao tác này.');
    }
}
