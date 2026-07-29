<?php

namespace App\Application\DTOs;

use App\Domain\Enums\UserRole;

final readonly class VerifiedToken
{
    public function __construct(
        public int $userId,
        public UserRole $role,
    ) {
    }
}
