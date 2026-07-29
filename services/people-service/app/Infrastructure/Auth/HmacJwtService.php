<?php

namespace App\Infrastructure\Auth;

use App\Application\DTOs\JwtToken;
use App\Application\DTOs\VerifiedToken;
use App\Application\Interfaces\JwtServiceInterface;
use App\Domain\Enums\UserRole;
use App\Domain\Exceptions\UnauthorizedException;
use DateTimeImmutable;
use JsonException;
use RuntimeException;

final class HmacJwtService implements JwtServiceInterface
{
    /**
     * @throws JsonException
     */
    public function issueToken(int $userId, UserRole $role): JwtToken
    {
        $secret = $this->jwtConfigString('secret');
        $issuer = $this->jwtConfigString('issuer');
        $audience = $this->jwtConfigString('audience');
        $ttlMinutes = max(1, (int) config('services.jwt.ttl_minutes'));
        $issuedAt = time();
        $expiresAt = $issuedAt + ($ttlMinutes * 60);

        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT',
        ];

        $payload = [
            'sub' => $userId,
            'role' => $role->value,
            'iss' => $issuer,
            'aud' => $audience,
            'iat' => $issuedAt,
            'exp' => $expiresAt,
        ];

        $encodedHeader = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $encodedPayload = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signingInput = $encodedHeader.'.'.$encodedPayload;
        $signature = hash_hmac('sha256', $signingInput, $secret, true);

        return new JwtToken(
            token: $signingInput.'.'.$this->base64UrlEncode($signature),
            expiresAt: new DateTimeImmutable('@'.$expiresAt),
        );
    }

    /**
     * @throws UnauthorizedException
     */
    public function verifyToken(string $token): VerifiedToken
    {
        $segments = explode('.', $token);

        if (count($segments) !== 3) {
            throw new UnauthorizedException('Token không hợp lệ.');
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $segments;

        $secret = $this->jwtConfigString('secret');
        $expectedSignature = hash_hmac('sha256', $encodedHeader.'.'.$encodedPayload, $secret, true);

        if (! hash_equals($this->base64UrlEncode($expectedSignature), $encodedSignature)) {
            throw new UnauthorizedException('Chữ ký token không hợp lệ.');
        }

        try {
            $payloadJson = base64_decode(strtr($encodedPayload, '-_', '+/'), strict: false);

            if ($payloadJson === false) {
                throw new UnauthorizedException('Token không thể giải mã.');
            }

            /** @var array<string, mixed> $claims */
            $claims = json_decode($payloadJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new UnauthorizedException('Payload token không hợp lệ.');
        }

        if (! isset($claims['exp']) || ! is_int($claims['exp']) || $claims['exp'] < time()) {
            throw new UnauthorizedException('Token đã hết hạn.');
        }

        // Xác minh issuer
        $expectedIssuer = $this->jwtConfigString('issuer');
        if (! isset($claims['iss']) || $claims['iss'] !== $expectedIssuer) {
            throw new UnauthorizedException('Token issuer không hợp lệ.');
        }

        // Xác minh audience
        $expectedAudience = $this->jwtConfigString('audience');
        if (! isset($claims['aud']) || $claims['aud'] !== $expectedAudience) {
            throw new UnauthorizedException('Token audience không hợp lệ.');
        }

        if (! isset($claims['sub']) || ! is_int($claims['sub'])) {
            throw new UnauthorizedException('Token thiếu claims bắt buộc.');
        }

        if (! isset($claims['role']) || ! is_string($claims['role'])) {
            throw new UnauthorizedException('Token thiếu role.');
        }

        $role = UserRole::tryFrom($claims['role']);

        if ($role === null) {
            throw new UnauthorizedException('Role trong token không hợp lệ.');
        }

        return new VerifiedToken(
            userId: $claims['sub'],
            role: $role,
        );
    }

    private function jwtConfigString(string $key): string
    {
        $value = config('services.jwt.'.$key);

        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException('JWT '.$key.' chưa được cấu hình.');
        }

        return $value;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}

