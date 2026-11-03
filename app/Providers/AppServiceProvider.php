<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        if ($this->app->isProduction() && (config('app.debug') || !config('session.secure') || !config('session.http_only') || !config('session.encrypt'))) {
            throw new \LogicException('Produksi memerlukan debug nonaktif, cookie secure/HttpOnly, dan sesi terenkripsi.');
        }
        RateLimiter::for('login', function (Request $request) {
            $email = is_string($request->input('email')) ? mb_strtolower(trim($request->input('email'))) : '';
            return [Limit::perMinute(5)->by('login:'.hash('sha256', $email.'|'.$request->ip())), Limit::perMinute(20)->by('login-ip:'.$request->ip())];
        });
        RateLimiter::for('orders', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('payments', fn (Request $request) => Limit::perMinute(15)->by($request->ip()));
        RateLimiter::for('payment-status', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
        RateLimiter::for('payment-webhook', fn (Request $request) => Limit::perMinute(300)->by($request->ip()));
        RateLimiter::for('catalog', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
        RateLimiter::for('admin-write', fn (Request $request) => Limit::perMinute(30)->by((string) $request->user()?->id));
    }
}
