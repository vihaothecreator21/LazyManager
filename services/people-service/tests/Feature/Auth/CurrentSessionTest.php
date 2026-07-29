<?php

namespace Tests\Feature\Auth;

use App\Application\Interfaces\JwtServiceInterface;
use App\Domain\Enums\UserRole;
use App\Domain\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class CurrentSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_with_valid_access_cookie_returns_user(): void
    {
        $user = $this->createUser();
        $token = $this->issueTokenFor($user);

        $this->withCredentials()->withUnencryptedCookie('lm_access_token', $token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonPath('user.role', UserRole::StoreManager->value);
    }

    public function test_me_without_token_returns_401(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_me_with_locked_user_returns_401_and_clears_cookies(): void
    {
        $user = $this->createUser(status: UserStatus::Locked);
        $token = $this->issueTokenFor($user);

        $this->withCredentials()->withUnencryptedCookie('lm_access_token', $token)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertCookieExpired('lm_access_token')
            ->assertCookieExpired('lm_refresh_token');
    }

    private function createUser(UserStatus $status = UserStatus::Active): User
    {
        return User::factory()->create([
            'name' => 'Test User',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => UserRole::StoreManager,
            'status' => $status,
        ]);
    }

    private function issueTokenFor(User $user): string
    {
        /** @var JwtServiceInterface $jwtService */
        $jwtService = $this->app->make(JwtServiceInterface::class);

        return $jwtService->issueToken($user->id, $user->role)->token;
    }
}
