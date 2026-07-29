<?php

namespace App\Application\DTOs;

use DateTimeImmutable;

final readonly class JwtToken
{
    public function __construct(
        public string $token,
        public DateTimeImmutable $expiresAt,
    ) {
    }
}
