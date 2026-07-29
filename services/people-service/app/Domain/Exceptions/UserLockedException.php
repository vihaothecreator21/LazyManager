<?php

namespace App\Domain\Exceptions;

use RuntimeException;

final class UserLockedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Tài khoản đã bị khóa.');
    }
}
