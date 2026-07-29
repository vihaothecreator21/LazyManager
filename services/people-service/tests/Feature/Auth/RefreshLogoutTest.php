<?php

namespace Tests\Feature\Auth;

use App\Domain\Enums\UserRole;
use App\Domain\Enums\UserStatus;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class RefreshLogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_refresh_rotates_refresh_token_and_sets_new_cookies(): void
    {
        $user = $this->createUser();
        $login = $this->loginAs($user);
        $oldRefreshToken = $this->cookieValue($login, 'lm_refresh_token');
        $csrfToken = $this->cookieValue($login, 'lm_csrf_token');

        $refresh = $this->withValidCsrf($csrfToken)
            ->withUnencryptedCookie('lm_refresh_token', $oldRefreshToken)
            ->postJson('/api/v1/auth/refresh');

        $refresh
            ->assertOk()
            ->assertCookie('lm_access_token')
            ->assertCookie('lm_refresh_token')
            ->assertCookie('lm_csrf_token')
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonPath('user.role', UserRole::StoreManager->value)
            ->assertJsonMissingPath('token')
            ->assertJsonMissingPath('expires_at');

        $this->assertSame(2, RefreshToken::query()->count());
        $this->assertSame(1, RefreshToken::query()->whereNotNull('revoked_at')->count());
        $this->assertSame(1, RefreshToken::query()->whereNull('revoked_at')->count());

        $newRefreshToken = $this->cookieValue($refresh, 'lm_refresh_token');
        $this->assertNotSame($oldRefreshToken, $newRefreshToken);
    }

    public function test_old_refresh_token_returns_401_after_rotation(): void
    {
        $login = $this->loginAs($this->createUser());
        $oldRefreshToken = $this->cookieValue($login, 'lm_refresh_token');
        $csrfToken = $this->cookieValue($login, 'lm_csrf_token');

        $this->withValidCsrf($csrfToken)
            ->withUnencryptedCookie('lm_refresh_token', $oldRefreshToken)
            ->postJson('/api/v1/auth/refresh')
            ->assertOk();

        $this->withValidCsrf($csrfToken)
            ->withUnencryptedCookie('lm_refresh_token', $oldRefreshToken)
            ->postJson('/api/v1/auth/refresh')
            ->assertUnauthorized()
            ->assertCookieExpired('lm_access_token')
            ->assertCookieExpired('lm_refresh_token');
    }

    public function test_missing_refresh_token_returns_401_and_clears_cookies(): void
    {
        $this->withValidCsrf()
            ->postJson('/api/v1/auth/refresh')
            ->assertUnauthorized()
            ->assertCookieExpired('lm_access_token')
            ->assertCookieExpired('lm_refresh_token');
    }

    public function test_refresh_without_csrf_returns_419(): void
    {
        $login = $this->loginAs($this->createUser());
        $refreshToken = $this->cookieValue($login, 'lm_refresh_token');

        $this->withCredentials()
            ->withUnencryptedCookie('lm_refresh_token', $refreshToken)
            ->postJson('/api/v1/auth/refresh')
            ->assertStatus(419);
    }

    public function test_locked_user_refresh_returns_401_and_revokes_token(): void
    {
        $user = $this->createUser();
        $login = $this->loginAs($user);
        $refreshToken = $this->cookieValue($login, 'lm_refresh_token');
        $csrfToken = $this->cookieValue($login, 'lm_csrf_token');

        $user->forceFill(['status' => UserStatus::Locked])->save();

        $this->withValidCsrf($csrfToken)
            ->withUnencryptedCookie('lm_refresh_token', $refreshToken)
            ->postJson('/api/v1/auth/refresh')
            ->assertUnauthorized()
            ->assertCookieExpired('lm_access_token')
            ->assertCookieExpired('lm_refresh_token');

        $this->assertSame(1, RefreshToken::query()->whereNotNull('revoked_at')->count());
    }

    public function test_logout_revokes_refresh_token_and_clears_cookies(): void
    {
        $login = $this->loginAs($this->createUser());
        $refreshToken = $this->cookieValue($login, 'lm_refresh_token');
        $csrfToken = $this->cookieValue($login, 'lm_csrf_token');

        $this->withValidCsrf($csrfToken)
            ->withUnencryptedCookie('lm_refresh_token', $refreshToken)
            ->postJson('/api/v1/auth/logout')
            ->assertNoContent()
            ->assertCookieExpired('lm_access_token')
            ->assertCookieExpired('lm_refresh_token')
            ->assertCookieExpired('lm_csrf_token');

        $this->assertSame(1, RefreshToken::query()->whereNotNull('revoked_at')->count());
    }

    private function createUser(): User
    {
        return User::factory()->create([
            'name' => 'Người dùng kiểm thử',
            'email' => 'manager@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::StoreManager,
            'status' => UserStatus::Active,
        ]);
    }

    private function loginAs(User $user)
    {
        return $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();
    }

    private function cookieValue($response, string $name): string
    {
        $cookie = collect($response->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === $name);

        $this->assertNotNull($cookie);

        return $cookie->getValue();
    }

    private function withValidCsrf(string $token = 'test-csrf-token'): self
    {
        return $this
            ->withCredentials()
            ->withUnencryptedCookie('lm_csrf_token', $token)
            ->withHeader('X-CSRF-TOKEN', $token);
    }
}
