<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Separate buckets prevent customer polling from exhausting the login limit.
        RateLimiter::for('staff-login', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('queue-write', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));
        RateLimiter::for('ticket-status', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
    }
}
