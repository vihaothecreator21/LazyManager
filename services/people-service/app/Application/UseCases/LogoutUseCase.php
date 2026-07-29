<?php

namespace App\Application\UseCases;

use App\Application\Interfaces\RefreshTokenServiceInterface;

final readonly class LogoutUseCase
{
    public function __construct(
        private RefreshTokenServiceInterface $refreshTokenService,
    ) {
    }

    public function execute(?string $rawRefreshToken): void
    {
        if (! is_string($rawRefreshToken) || $rawRefreshToken === '') {
            return;
        }

        $refreshToken = $this->refreshTokenService->findActiveToken($rawRefreshToken);

        if ($refreshToken === null) {
            return;
        }

        $this->refreshTokenService->revokeToken($refreshToken);
    }
}
