<?php

namespace Tests\Feature\Auth;

use App\Application\Interfaces\JwtServiceInterface;
use App\Domain\Enums\UserRole;
use App\Domain\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Kiểm thử AuthenticateJwt middleware và ManagerOnly middleware.
 *
 * Các case bắt buộc theo skill 06-authentication-authorization §9:
 * - Không có access cookie → 401
 * - Token sai chữ ký → 401
 * - Token hết hạn → 401
 * - Staff POST /employees → 403
 * - Manager POST /employees → middleware cho qua (trả 501 vì controller stub)
 */
final class MiddlewareTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // AuthenticateJwt: no token
    // -------------------------------------------------------------------------

    public function test_no_access_cookie_returns_401(): void
    {
        $this->postJson('/api/v1/employees')
            ->assertUnauthorized();
    }

    public function test_authorization_header_without_cookie_returns_401(): void
    {
        $this->withHeader('Authorization', 'Bearer '.$this->issueTokenForManager())
            ->postJson('/api/v1/employees')
            ->assertUnauthorized();
    }

    // -------------------------------------------------------------------------
    // AuthenticateJwt: sai chữ ký
    // -------------------------------------------------------------------------

    public function test_wrong_signature_returns_401(): void
    {
        $token = $this->issueTokenForManager();

        // Thay đổi một ký tự ở signature (phần thứ 3)
        $parts = explode('.', $token);
        $parts[2] = strrev($parts[2]); // đảo ngược signature → sai
        $tampered = implode('.', $parts);

        $this->withCredentials()->withUnencryptedCookie('lm_access_token', $tampered)
            ->postJson('/api/v1/employees')
            ->assertUnauthorized();
    }

    public function test_garbage_token_returns_401(): void
    {
        $this->withCredentials()->withUnencryptedCookie('lm_access_token', 'not.a.valid.jwt.at.all')
            ->postJson('/api/v1/employees')
            ->assertUnauthorized();
    }

    // -------------------------------------------------------------------------
    // AuthenticateJwt: hết hạn
    // -------------------------------------------------------------------------

    public function test_expired_token_returns_401(): void
    {
        $user = $this->createUser(role: UserRole::StoreManager);

        $expiredToken = $this->issueExpiredToken($user->id, $user->role);

        $this->withCredentials()->withUnencryptedCookie('lm_access_token', $expiredToken)
            ->postJson('/api/v1/employees')
            ->assertUnauthorized();
    }

    // -------------------------------------------------------------------------
    // AuthenticateJwt: iss / aud sai
    // -------------------------------------------------------------------------

    public function test_wrong_issuer_returns_401(): void
    {
        $token = $this->craftTokenWithClaims(['iss' => 'evil-service']);

        $this->withCredentials()->withUnencryptedCookie('lm_access_token', $token)
            ->postJson('/api/v1/employees')
            ->assertUnauthorized();
    }

    public function test_wrong_audience_returns_401(): void
    {
        $token = $this->craftTokenWithClaims(['aud' => 'evil-audience']);

        $this->withCredentials()->withUnencryptedCookie('lm_access_token', $token)
            ->postJson('/api/v1/employees')
            ->assertUnauthorized();
    }

    // -------------------------------------------------------------------------
    // ManagerOnly: STAFF bị chặn
    // -------------------------------------------------------------------------

    public function test_staff_post_employees_returns_403(): void
    {
        $staff = $this->createUser(role: UserRole::Staff);
        $token = $this->issueTokenFor(UserRole::Staff, $staff->id);

        $this->withValidCsrf()->withUnencryptedCookie('lm_access_token', $token)
            ->postJson('/api/v1/employees', ['name' => 'Test'])
            ->assertForbidden();
    }

    public function test_staff_post_employees_without_csrf_returns_419(): void
    {
        $staff = $this->createUser(role: UserRole::Staff);
        $token = $this->issueTokenFor(UserRole::Staff, $staff->id);

        $this->withCredentials()->withUnencryptedCookie('lm_access_token', $token)
            ->postJson('/api/v1/employees', ['name' => 'Test'])
            ->assertStatus(419);
    }

    public function test_staff_put_employees_returns_403(): void
    {
        $staff = $this->createUser(role: UserRole::Staff);
        $token = $this->issueTokenFor(UserRole::Staff, $staff->id);

        $this->withValidCsrf()->withUnencryptedCookie('lm_access_token', $token)
            ->putJson('/api/v1/employees/1', ['name' => 'Test'])
            ->assertForbidden();
    }

    public function test_staff_delete_employees_returns_403(): void
    {
        $staff = $this->createUser(role: UserRole::Staff);
        $token = $this->issueTokenFor(UserRole::Staff, $staff->id);

        $this->withValidCsrf()->withUnencryptedCookie('lm_access_token', $token)
            ->deleteJson('/api/v1/employees/1')
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // ManagerOnly: STORE_MANAGER được phép vào controller
    // -------------------------------------------------------------------------

    public function test_manager_post_employees_passes_middleware(): void
    {
        $token = $this->issueTokenForManager();

        // Controller stub trả 501 — middleware không chặn
        $this->withValidCsrf()->withUnencryptedCookie('lm_access_token', $token)
            ->postJson('/api/v1/employees', [
                'name' => 'Test',
                'email' => 'created@example.com',
                'password' => 'password123',
                'role' => UserRole::Staff->value,
                'status' => UserStatus::Active->value,
            ])
            ->assertCreated();
    }

    public function test_manager_put_employees_passes_middleware(): void
    {
        $token = $this->issueTokenForManager();

        $this->withValidCsrf()->withUnencryptedCookie('lm_access_token', $token)
            ->putJson('/api/v1/employees/1', ['name' => 'Test'])
            ->assertNotFound();
    }

    public function test_manager_delete_employees_passes_middleware(): void
    {
        $token = $this->issueTokenForManager();

        $this->withValidCsrf()->withUnencryptedCookie('lm_access_token', $token)
            ->deleteJson('/api/v1/employees/1')
            ->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createUser(UserRole $role = UserRole::StoreManager): User
    {
        return User::factory()->create([
            'password' => Hash::make('password'),
            'role'     => $role,
            'status'   => UserStatus::Active,
        ]);
    }

    private function issueTokenForManager(): string
    {
        $user = $this->createUser(role: UserRole::StoreManager);

        return $this->issueTokenFor(UserRole::StoreManager, $user->id);
    }

    private function withValidCsrf(): self
    {
        return $this
            ->withCredentials()
            ->withUnencryptedCookie('lm_csrf_token', 'test-csrf-token')
            ->withHeader('X-CSRF-TOKEN', 'test-csrf-token');
    }

    private function issueTokenFor(UserRole $role, ?int $userId = null): string
    {
        /** @var JwtServiceInterface $jwtService */
        $jwtService = $this->app->make(JwtServiceInterface::class);

        return $jwtService->issueToken($userId ?? 999, $role)->token;
    }

    /**
     * Phát token với exp = now() - 1 (đã hết hạn ngay lập tức).
     */
    private function issueExpiredToken(int $userId, UserRole $role): string
    {
        $secret = config('services.jwt.secret');
        $issuer = config('services.jwt.issuer');
        $audience = config('services.jwt.audience');

        $now = time();

        $header  = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = base64_encode(json_encode([
            'sub' => $userId,
            'role' => $role->value,
            'iss' => $issuer,
            'aud' => $audience,
            'iat' => $now - 7200,
            'exp' => $now - 1, // hết hạn 1 giây trước
        ]));

        $encodeUrl = fn (string $b64) => rtrim(strtr($b64, '+/', '-_'), '=');

        $h = $encodeUrl($header);
        $p = $encodeUrl($payload);
        $sig = $encodeUrl(base64_encode(hash_hmac('sha256', "$h.$p", $secret, true)));

        return "$h.$p.$sig";
    }

    /**
     * Tạo token hợp lệ rồi ghi đè một số claims để kiểm thử xác minh claims.
     *
     * @param array<string, mixed> $overrides
     */
    private function craftTokenWithClaims(array $overrides): string
    {
        $secret = config('services.jwt.secret');
        $issuer = config('services.jwt.issuer');
        $audience = config('services.jwt.audience');
        $now = time();

        $claims = array_merge([
            'sub'  => 1,
            'role' => UserRole::StoreManager->value,
            'iss'  => $issuer,
            'aud'  => $audience,
            'iat'  => $now,
            'exp'  => $now + 7200,
        ], $overrides);

        $encodeUrl = fn (string $b64) => rtrim(strtr($b64, '+/', '-_'), '=');

        $h = $encodeUrl(base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])));
        $p = $encodeUrl(base64_encode(json_encode($claims)));
        $sig = $encodeUrl(base64_encode(hash_hmac('sha256', "$h.$p", $secret, true)));

        return "$h.$p.$sig";
    }
}


