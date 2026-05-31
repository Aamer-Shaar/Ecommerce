<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

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
try {
        \Illuminate\Support\Facades\Cache::store('redis')->get('health_check');
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::warning('Redis unavailable, switching to file cache');
        config(['cache.default' => 'file']);
    }
    
    RateLimiter::for('checkout_process', function (Request $request) {
        // السماح بـ5 طلبات فقط كل دقيقة لكل مستخدم أو IP
        return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip())
            ->response(function (Request $request, array $headers) {
                return response()->json([
                    'message' => 'You have exceeded the allowed order limit. Please wait a moment to protect system resources..'
                ], 429, $headers);
            });
    });

    RateLimiter::for('register_limit', function (Request $request) {
    return Limit::perMinute(3)->by($request->ip())
        ->response(function () {
            return response()->json([
                'message' => 'Too many registration attempts. Please try again later.'
            ], 429);
        });
});

    RateLimiter::for('login_limit', function (Request $request) {
    return Limit::perMinute(3)->by($request->ip())
        ->response(function () {
            return response()->json([
                'message' => 'Too many login attempts, try again later.'
            ], 429);
        });
});

    RateLimiter::for('cart_limit', function (Request $request) {
    return Limit::perMinute(10)->by($request->user()->id?: $request->ip())->response(function () {
    return response()->json([
        'success' => false,
        'message' => 'Too many requests. Please try again later.'
    ], 429);
});
    
});

    RateLimiter::for('api_general', function (Request $request) {
    return Limit::perMinute(200)->by($request->user()?->id ?: $request->ip())->response(function () {
    return response()->json([
        'success' => false,
        'message' => 'Too many requests. Please try again later.'
    ], 429);
});
});
    }
}
