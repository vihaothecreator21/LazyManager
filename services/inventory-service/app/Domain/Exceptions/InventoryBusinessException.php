<?php

namespace App\Domain\Exceptions;

use RuntimeException;

class InventoryBusinessException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}
