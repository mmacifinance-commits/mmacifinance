<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
        \Illuminate\Support\Facades\RateLimiter::for('otp-verify', fn (\Illuminate\Http\Request $request) => [
            \Illuminate\Cache\RateLimiting\Limit::perMinute(10)->by('ip:'.$request->ip()),
            \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by('user:'.($request->session()->get('2fa_user_id') ?? $request->ip())),
        ]);
    }
}
