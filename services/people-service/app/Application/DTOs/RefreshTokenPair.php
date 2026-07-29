<?php

namespace App\Application\DTOs;

use DateTimeImmutable;

final readonly class RefreshTokenPair
{
    public function __construct(
        public string $rawToken,
        public DateTimeImmutable $expiresAt,
    ) {
    }
}
