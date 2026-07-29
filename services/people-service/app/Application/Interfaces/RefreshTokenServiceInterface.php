<?php

namespace App\Application\Interfaces;

use App\Application\DTOs\RefreshTokenPair;
use App\Models\RefreshToken;
use App\Models\User;

interface RefreshTokenServiceInterface
{
    public function issueToken(User $user): RefreshTokenPair;

    public function findActiveToken(string $rawToken): ?RefreshToken;

    public function revokeToken(RefreshToken $refreshToken): void;
}
