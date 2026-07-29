<?php

namespace App\Http\Controllers;

use App\Application\DTOs\LoginData;
use App\Application\DTOs\VerifiedToken;
use App\Application\UseCases\LoginUseCase;
use App\Application\UseCases\LogoutUseCase;
use App\Application\UseCases\RefreshSessionUseCase;
use App\Domain\Enums\UserStatus;
use App\Domain\Exceptions\InvalidLoginCredentialsException;
use App\Domain\Exceptions\UnauthorizedException;
use App\Domain\Exceptions\UserLockedException;
use App\Http\Requests\LoginRequest;
use App\Http\Support\AuthCookieFactory;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuthController extends Controller
{
    public function __construct(
        private readonly LoginUseCase $loginUseCase,
        private readonly RefreshSessionUseCase $refreshSessionUseCase,
        private readonly LogoutUseCase $logoutUseCase,
        private readonly AuthCookieFactory $authCookieFactory,
    ) {
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $result = $this->loginUseCase->execute(new LoginData(
                email: (string) $validated['email'],
                password: (string) $validated['password'],
            ));
        } catch (InvalidLoginCredentialsException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 401);
        } catch (UserLockedException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 403);
        }

        return response()->json([
            'user' => [
                'id' => $result->userId,
                'email' => $result->email,
                'role' => $result->role->value,
            ],
        ])
            ->cookie($this->authCookieFactory->accessToken($result->jwt->token))
            ->cookie($this->authCookieFactory->refreshToken($result->refreshToken->rawToken))
            ->cookie($this->authCookieFactory->csrfToken());
    }

    public function refresh(Request $request): JsonResponse
    {
        $rawRefreshToken = $request->cookie((string) config('services.auth_cookies.refresh_cookie', 'lm_refresh_token'));

        if (! is_string($rawRefreshToken) || $rawRefreshToken === '') {
            return $this->unauthorizedRefreshResponse();
        }

        try {
            $session = $this->refreshSessionUseCase->execute($rawRefreshToken);
        } catch (UnauthorizedException) {
            return $this->unauthorizedRefreshResponse();
        }

        return response()->json([
            'user' => [
                'id' => $session->userId,
                'email' => $session->email,
                'role' => $session->role->value,
            ],
        ])
            ->cookie($this->authCookieFactory->accessToken($session->jwt->token))
            ->cookie($this->authCookieFactory->refreshToken($session->refreshToken->rawToken))
            ->cookie($this->authCookieFactory->csrfToken());
    }

    public function me(Request $request): JsonResponse
    {
        $verified = $request->attributes->get('verified_token');

        if (! $verified instanceof VerifiedToken) {
            return $this->unauthorizedSessionResponse();
        }

        $user = User::query()->find($verified->userId);

        if (! $user instanceof User || $user->status === UserStatus::Locked) {
            return $this->unauthorizedSessionResponse();
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'role' => $user->role->value,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $rawRefreshToken = $request->cookie((string) config('services.auth_cookies.refresh_cookie', 'lm_refresh_token'));

        $this->logoutUseCase->execute(is_string($rawRefreshToken) ? $rawRefreshToken : null);

        return response()->json(null, 204)
            ->cookie($this->authCookieFactory->forgetAccessToken())
            ->cookie($this->authCookieFactory->forgetRefreshToken())
            ->cookie($this->authCookieFactory->forgetCsrfToken());
    }

    private function unauthorizedRefreshResponse(): JsonResponse
    {
        return response()->json(['message' => 'Phiên đăng nhập không hợp lệ.'], 401)
            ->cookie($this->authCookieFactory->forgetAccessToken())
            ->cookie($this->authCookieFactory->forgetRefreshToken())
            ->cookie($this->authCookieFactory->forgetCsrfToken());
    }

    private function unauthorizedSessionResponse(): JsonResponse
    {
        return response()->json(['message' => 'Phiên đăng nhập không hợp lệ.'], 401)
            ->cookie($this->authCookieFactory->forgetAccessToken())
            ->cookie($this->authCookieFactory->forgetRefreshToken())
            ->cookie($this->authCookieFactory->forgetCsrfToken());
    }
}

