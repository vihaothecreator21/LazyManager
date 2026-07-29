<?php

namespace App\Application\DTOs;

use App\Domain\Enums\UserRole;

final readonly class AuthSession
{
    public function __construct(
        public int $userId,
        public string $email,
        public UserRole $role,
        public JwtToken $jwt,
        public RefreshTokenPair $refreshToken,
    ) {
    }
}
