<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class InventoryFeatureTestCase extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    protected function authHeaders(): array
    {
        return ['X-CSRF-TOKEN' => 'inventory-csrf'];
    }

    protected function actingWithInventoryCookie(string $role = 'STORE_MANAGER', int $userId = 1): self
    {
        return $this
            ->withCredentials()
            ->withUnencryptedCookie('lm_access_token', $this->issueToken(userId: $userId, role: $role))
            ->withUnencryptedCookie('lm_csrf_token', 'inventory-csrf');
    }

    protected function issueToken(
        int $userId = 1,
        string $role = 'STORE_MANAGER',
        ?int $expiresAt = null,
    ): string {
        $now = time();
        $expiresAt ??= $now + 900;

        $header = $this->base64UrlEncode(json_encode([
            'alg' => 'HS256',
            'typ' => 'JWT',
        ], JSON_THROW_ON_ERROR));

        $payload = $this->base64UrlEncode(json_encode([
            'sub' => $userId,
            'role' => $role,
            'iss' => config('services.jwt.issuer'),
            'aud' => config('services.jwt.audience'),
            'iat' => $now,
            'exp' => $expiresAt,
        ], JSON_THROW_ON_ERROR));

        $signature = $this->base64UrlEncode(hash_hmac(
            'sha256',
            $header.'.'.$payload,
            (string) config('services.jwt.secret'),
            true,
        ));

        return $header.'.'.$payload.'.'.$signature;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
