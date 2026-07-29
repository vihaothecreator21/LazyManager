<?php

namespace App\Http\Support;

use Symfony\Component\HttpFoundation\Cookie;
use Illuminate\Support\Str;

final class AuthCookieFactory
{
    public function accessToken(string $token): Cookie
    {
        return $this->make(
            name: $this->configString('access_cookie'),
            value: $token,
            minutes: $this->configInt('access_ttl_minutes'),
        );
    }

    public function refreshToken(string $token): Cookie
    {
        return $this->make(
            name: $this->configString('refresh_cookie'),
            value: $token,
            minutes: $this->configInt('refresh_ttl_minutes'),
        );
    }

    public function csrfToken(?string $token = null): Cookie
    {
        return $this->make(
            name: $this->configString('csrf_cookie'),
            value: $token ?? Str::random(40),
            minutes: $this->configInt('refresh_ttl_minutes'),
            httpOnly: false,
        );
    }

    public function forgetAccessToken(): Cookie
    {
        return $this->forget($this->configString('access_cookie'));
    }

    public function forgetRefreshToken(): Cookie
    {
        return $this->forget($this->configString('refresh_cookie'));
    }

    public function forgetCsrfToken(): Cookie
    {
        return $this->forget($this->configString('csrf_cookie'));
    }

    private function make(string $name, string $value, int $minutes, bool $httpOnly = true): Cookie
    {
        return cookie(
            name: $name,
            value: $value,
            minutes: $minutes,
            path: '/',
            domain: null,
            secure: (bool) config('services.auth_cookies.secure'),
            httpOnly: $httpOnly,
            raw: false,
            sameSite: $this->configString('same_site'),
        );
    }

    private function forget(string $name): Cookie
    {
        return cookie()->forget(
            name: $name,
            path: '/',
            domain: null,
        );
    }

    private function configString(string $key): string
    {
        $value = config('services.auth_cookies.'.$key);

        return is_string($value) && $value !== '' ? $value : '';
    }

    private function configInt(string $key): int
    {
        return max(1, (int) config('services.auth_cookies.'.$key));
    }
}
