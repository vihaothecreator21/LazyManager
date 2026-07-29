<?php

namespace App\Http\Middleware;

use App\Application\Interfaces\JwtServiceInterface;
use App\Domain\Exceptions\UnauthorizedException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateJwt
{
    public function __construct(
        private readonly JwtServiceInterface $jwtService,
    ) {
    }

    /**
     * @param Closure(Request): Response $next
     */
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

        $request->attributes->set('verified_token', $verified);

        return $next($request);
    }
}
