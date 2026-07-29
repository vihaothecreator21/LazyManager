<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class VerifyDoubleSubmitCsrf
{
    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        $cookieName = (string) config('services.auth_cookies.csrf_cookie', 'lm_csrf_token');
        $cookieToken = $request->cookie($cookieName);
        $headerToken = $request->header('X-CSRF-TOKEN');

        if (! is_string($cookieToken) || ! is_string($headerToken) || ! hash_equals($cookieToken, $headerToken)) {
            return response()->json(['message' => 'CSRF token không hợp lệ.'], 419);
        }

        return $next($request);
    }
}
