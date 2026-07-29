<?php

namespace App\Providers;

use App\Application\Interfaces\JwtServiceInterface;
use App\Infrastructure\Auth\HmacJwtService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(JwtServiceInterface::class, HmacJwtService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
