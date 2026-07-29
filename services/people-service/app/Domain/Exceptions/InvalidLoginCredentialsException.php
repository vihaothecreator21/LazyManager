<?php

namespace App\Domain\Exceptions;

use RuntimeException;

final class InvalidLoginCredentialsException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Email hoặc mật khẩu không đúng.');
    }
}
