<?php

namespace App\Application\UseCases;

use App\Application\DTOs\LoginData;
use App\Application\DTOs\LoginResult;
use App\Application\Interfaces\JwtServiceInterface;
use App\Application\Interfaces\RefreshTokenServiceInterface;
use App\Domain\Enums\UserStatus;
use App\Domain\Exceptions\InvalidLoginCredentialsException;
use App\Domain\Exceptions\UserLockedException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

final readonly class LoginUseCase
{
    public function __construct(
        private JwtServiceInterface $jwtService,
        private RefreshTokenServiceInterface $refreshTokenService,
    ) {
    }

    public function execute(LoginData $data): LoginResult
    {
        $user = User::query()
            ->where('email', $data->email)
            ->first();

        $passwordMatches = $user ? Hash::check($data->password, $user->password) : false;

        if (! $user || ! $passwordMatches) {
            throw new InvalidLoginCredentialsException();
        }

        if ($user->status === UserStatus::Locked) {
            throw new UserLockedException();
        }

        return new LoginResult(
            userId: $user->id,
            email: $user->email,
            role: $user->role,
            jwt: $this->jwtService->issueToken($user->id, $user->role),
            refreshToken: $this->refreshTokenService->issueToken($user),
        );
    }
}
