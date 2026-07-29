<?php

namespace App\Application\Interfaces;

use App\Application\DTOs\JwtToken;
use App\Application\DTOs\VerifiedToken;
use App\Domain\Enums\UserRole;

interface JwtServiceInterface
{
    public function issueToken(int $userId, UserRole $role): JwtToken;

    /**
     * Xác minh chữ ký và thời hạn của JWT.
     * Ném UnauthorizedException nếu token không hợp lệ hoặc hết hạn.
     *
     * @throws \App\Domain\Exceptions\UnauthorizedException
     */
    public function verifyToken(string $token): VerifiedToken;
}
