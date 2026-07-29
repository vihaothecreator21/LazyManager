<?php

namespace App\Domain\Exceptions;

use RuntimeException;

final class UnauthorizedException extends RuntimeException
{
    public function __construct(string $reason = 'Bạn chưa xác thực.')
    {
        parent::__construct($reason);
    }
}
