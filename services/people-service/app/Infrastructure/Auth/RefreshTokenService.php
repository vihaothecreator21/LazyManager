<?php

namespace App\Infrastructure\Auth;

use App\Application\DTOs\RefreshTokenPair;
use App\Application\Interfaces\RefreshTokenServiceInterface;
use App\Models\RefreshToken;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Support\Str;

final class RefreshTokenService implements RefreshTokenServiceInterface
{
    public function issueToken(User $user): RefreshTokenPair
    {
        $ttlMinutes = max(1, (int) config('services.auth_cookies.refresh_ttl_minutes'));
        $rawToken = Str::random(80);
        $expiresAt = new DateTimeImmutable('+'.$ttlMinutes.' minutes');

        RefreshToken::query()->create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => $expiresAt,
        ]);

        return new RefreshTokenPair(
            rawToken: $rawToken,
            expiresAt: $expiresAt,
        );
    }

    public function findActiveToken(string $rawToken): ?RefreshToken
    {
        return RefreshToken::query()
            ->with('user')
            ->where('token_hash', hash('sha256', $rawToken))
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    public function revokeToken(RefreshToken $refreshToken): void
    {
        if ($refreshToken->revoked_at !== null) {
            return;
        }

        $refreshToken->forceFill(['revoked_at' => now()])->save();
    }
}
