<?php

namespace Tests\Feature;

use Tests\TestCase;

final class InventoryAuthTest extends TestCase
{
    public function test_missing_access_cookie_returns_401(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_wrong_signature_returns_401(): void
    {
        $token = $this->issueToken();
        $parts = explode('.', $token);
        $parts[2] = strrev($parts[2]);

        $this->withCredentials()
            ->withUnencryptedCookie('lm_access_token', implode('.', $parts))
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_expired_token_returns_401(): void
    {
        $token = $this->issueToken(expiresAt: time() - 1);

        $this->withCredentials()
            ->withUnencryptedCookie('lm_access_token', $token)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_store_manager_role_is_read_from_cookie_jwt(): void
    {
        $token = $this->issueToken(userId: 10, role: 'STORE_MANAGER');

        $this->withCredentials()
            ->withUnencryptedCookie('lm_access_token', $token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.id', 10)
            ->assertJsonPath('user.role', 'STORE_MANAGER');
    }

    public function test_staff_role_is_read_from_cookie_jwt(): void
    {
        $token = $this->issueToken(userId: 20, role: 'STAFF');

        $this->withCredentials()
            ->withUnencryptedCookie('lm_access_token', $token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.id', 20)
            ->assertJsonPath('user.role', 'STAFF');
    }

    public function test_state_changing_request_without_csrf_returns_419(): void
    {
        $token = $this->issueToken();

        $this->withCredentials()
            ->withUnencryptedCookie('lm_access_token', $token)
            ->postJson('/api/v1/auth/probe')
            ->assertStatus(419);
    }

    public function test_state_changing_request_with_csrf_passes(): void
    {
        $token = $this->issueToken(userId: 30, role: 'STAFF');

        $this->withCredentials()
            ->withUnencryptedCookie('lm_access_token', $token)
            ->withUnencryptedCookie('lm_csrf_token', 'inventory-csrf')
            ->withHeader('X-CSRF-TOKEN', 'inventory-csrf')
            ->postJson('/api/v1/auth/probe')
            ->assertOk()
            ->assertJsonPath('user.id', 30)
            ->assertJsonPath('user.role', 'STAFF');
    }

    private function issueToken(
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
