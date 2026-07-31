<?php

namespace App\Application\UseCases;

use App\Application\DTOs\AuthSession;
use App\Application\Interfaces\JwtServiceInterface;
use App\Application\Interfaces\RefreshTokenServiceInterface;
use App\Domain\Enums\UserStatus;
use App\Domain\Exceptions\UnauthorizedException;
use Illuminate\Support\Facades\DB;

final readonly class RefreshSessionUseCase
{
    public function __construct(
        private JwtServiceInterface $jwtService,
        private RefreshTokenServiceInterface $refreshTokenService,
    ) {
    }

    /**
     * @throws UnauthorizedException
     */
    public function execute(string $rawRefreshToken): AuthSession
    {
        return DB::transaction(function () use ($rawRefreshToken): AuthSession {
            $refreshToken = $this->refreshTokenService->findActiveTokenForUpdate($rawRefreshToken);

            if ($refreshToken === null || $refreshToken->user === null) {
                throw new UnauthorizedException('Phiên đăng nhập không hợp lệ.');
            }

            $user = $refreshToken->user;

            if ($user->status === UserStatus::Locked) {
                $this->refreshTokenService->revokeToken($refreshToken);

                throw new UnauthorizedException('Phiên đăng nhập không hợp lệ.');
            }

            $this->refreshTokenService->revokeToken($refreshToken);
            $newRefreshToken = $this->refreshTokenService->issueToken($user);

            return new AuthSession(
                userId: $user->id,
                email: $user->email,
                role: $user->role,
                jwt: $this->jwtService->issueToken($user->id, $user->role),
                refreshToken: $newRefreshToken,
            );
        });
    }
}
