<?php

namespace App\Application\DTOs;

use App\Domain\Enums\UserRole;

final readonly class VerifiedToken
{
    public function __construct(
        public readonly int $userId,
        public readonly UserRole $role,
    ) {}
}
