<?php

namespace App\Http\Middleware;

use App\Application\Interfaces\JwtServiceInterface;
use App\Application\DTOs\VerifiedToken;
use App\Domain\Enums\UserStatus;
use App\Domain\Exceptions\UnauthorizedException;
use App\Http\Support\AuthCookieFactory;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateJwt
{
    public function __construct(
        private readonly JwtServiceInterface $jwtService,
        private readonly AuthCookieFactory $authCookieFactory,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->cookie((string) config('services.auth_cookies.access_cookie', 'lm_access_token'));

        if (! is_string($token) || $token === '') {
            return response()->json(['message' => 'Bạn chưa xác thực.'], 401);
        }

        try {
            $verified = $this->jwtService->verifyToken($token);
        } catch (UnauthorizedException $e) {
            return response()->json(['message' => $e->getMessage()], 401);
        }

        $user = User::query()->find($verified->userId);

        if (! $user instanceof User || $user->status !== UserStatus::Active) {
            return response()->json(['message' => 'Phiên đăng nhập không hợp lệ.'], 401)
                ->cookie($this->authCookieFactory->forgetAccessToken())
                ->cookie($this->authCookieFactory->forgetRefreshToken())
                ->cookie($this->authCookieFactory->forgetCsrfToken());
        }

        $request->attributes->set('verified_token', new VerifiedToken(
            userId: $user->id,
            role: $user->role,
        ));

        return $next($request);
    }
}
