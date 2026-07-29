<?php

namespace App\Application\Interfaces;

use App\Application\DTOs\VerifiedToken;

interface JwtServiceInterface
{
    public function verifyToken(string $token): VerifiedToken;
}
