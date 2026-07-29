<?php

namespace App\Infrastructure\Auth;

use App\Application\DTOs\VerifiedToken;
use App\Application\Interfaces\JwtServiceInterface;
use App\Domain\Enums\UserRole;
use App\Domain\Exceptions\UnauthorizedException;
use JsonException;
use RuntimeException;

final class HmacJwtService implements JwtServiceInterface
{
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

        if (! isset($claims['iss']) || $claims['iss'] !== $this->jwtConfigString('issuer')) {
            throw new UnauthorizedException('Token issuer không hợp lệ.');
        }

        if (! isset($claims['aud']) || $claims['aud'] !== $this->jwtConfigString('audience')) {
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
            $value = env('JWT_'.strtoupper($key));
        }

        if (! is_string($value) || trim($value) === '') {
            $value = getenv('JWT_'.strtoupper($key));
        }

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
