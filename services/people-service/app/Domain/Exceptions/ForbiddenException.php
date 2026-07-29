<?php

namespace App\Domain\Exceptions;

use RuntimeException;

final class ForbiddenException extends RuntimeException
{
    public function __construct(string $reason = 'Bạn không có quyền thực hiện thao tác này.')
    {
        parent::__construct($reason);
    }
}
