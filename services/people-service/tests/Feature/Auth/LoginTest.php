<?php

namespace Tests\Feature\Auth;

use App\Domain\Enums\UserRole;
use App\Domain\Enums\UserStatus;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_manager_can_login(): void
    {
        $user = $this->createUser(
            email: 'manager@example.com',
            role: UserRole::StoreManager,
        );

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'manager@example.com',
            'password' => 'password',
        ]);

        $response
            ->assertOk()
            ->assertCookie('lm_access_token')
            ->assertCookie('lm_refresh_token')
            ->assertCookie('lm_csrf_token')
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', 'manager@example.com')
            ->assertJsonPath('user.role', UserRole::StoreManager->value)
            ->assertJsonMissingPath('user.password')
            ->assertJsonMissingPath('token')
            ->assertJsonMissingPath('expires_at')
            ->assertJsonStructure([
                'user' => ['id', 'email', 'role'],
            ]);

        $this->assertDatabaseHas('refresh_tokens', [
            'user_id' => $user->id,
            'revoked_at' => null,
        ]);
        $this->assertSame(1, RefreshToken::query()->count());

        $accessCookie = collect($response->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === 'lm_access_token');
        $this->assertNotNull($accessCookie);
        $payload = $this->decodeJwtPayload($accessCookie->getValue());

        $this->assertSame($user->id, $payload['sub']);
        $this->assertSame(UserRole::StoreManager->value, $payload['role']);
        $this->assertSame('lazymanager-people-service', $payload['iss']);
        $this->assertSame('lazymanager', $payload['aud']);
        $this->assertArrayHasKey('iat', $payload);
        $this->assertArrayHasKey('exp', $payload);
    }

    public function test_staff_can_login(): void
    {
        $this->createUser(
            email: 'staff@example.com',
            role: UserRole::Staff,
        );

        $this->postJson('/api/v1/auth/login', [
            'email' => 'staff@example.com',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertCookie('lm_access_token')
            ->assertCookie('lm_refresh_token')
            ->assertCookie('lm_csrf_token')
            ->assertJsonPath('user.role', UserRole::Staff->value);
    }

    public function test_wrong_password_returns_401(): void
    {
        $this->createUser(email: 'manager@example.com');

        $this->postJson('/api/v1/auth/login', [
            'email' => 'manager@example.com',
            'password' => 'wrong-password',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Email hoặc mật khẩu không đúng.');
    }

    public function test_unknown_email_returns_401(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'missing@example.com',
            'password' => 'password',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Email hoặc mật khẩu không đúng.');
    }

    public function test_locked_user_returns_403(): void
    {
        $this->createUser(
            email: 'locked@example.com',
            status: UserStatus::Locked,
        );

        $this->postJson('/api/v1/auth/login', [
            'email' => 'locked@example.com',
            'password' => 'password',
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Tài khoản đã bị khóa.');
    }

    public function test_validation_error_returns_422(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => '',
            'password' => '',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    private function createUser(
        string $email,
        UserRole $role = UserRole::StoreManager,
        UserStatus $status = UserStatus::Active,
    ): User {
        return User::factory()->create([
            'name' => 'Người dùng kiểm thử',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => $status,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJwtPayload(string $token): array
    {
        $segments = explode('.', $token);

        $this->assertCount(3, $segments);

        $payload = strtr($segments[1], '-_', '+/');
        $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);

        return json_decode(base64_decode($payload), true, flags: JSON_THROW_ON_ERROR);
    }
}
