<?php

namespace App\Providers;

use App\Application\Interfaces\JwtServiceInterface;
use App\Application\Interfaces\RefreshTokenServiceInterface;
use App\Infrastructure\Auth\HmacJwtService;
use App\Infrastructure\Auth\RefreshTokenService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(JwtServiceInterface::class, HmacJwtService::class);
        $this->app->bind(RefreshTokenServiceInterface::class, RefreshTokenService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
