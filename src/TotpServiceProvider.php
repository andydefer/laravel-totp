<?php

declare(strict_types=1);

namespace AndyDefer\LaravelTotp;

use AndyDefer\LaravelTotp\Services\QrCodeGenerator;
use AndyDefer\LaravelTotp\Services\TotpGenerator;
use AndyDefer\LaravelTotp\Services\TotpService;
use Illuminate\Support\ServiceProvider;

final class TotpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TotpGenerator::class);
        $this->app->singleton(QrCodeGenerator::class);
        $this->app->singleton(TotpService::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'Totp-migrations');
    }
}