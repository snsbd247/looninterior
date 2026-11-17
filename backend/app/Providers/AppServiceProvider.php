<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Older MySQL / MariaDB on shared hosting: utf8mb4 index length limit
        Schema::defaultStringLength(191);

        // Separate counters per action, so e.g. submitting forms never
        // uses up the admin login attempts (plain "throttle:x,y" shares one key per IP).
        RateLimiter::for('forms', fn (Request $r) => Limit::perMinute(10)->by('forms|'.$r->ip()));
        RateLimiter::for('login', fn (Request $r) => Limit::perMinute(10)->by('login|'.$r->ip()));
        RateLimiter::for('portal-login', fn (Request $r) => Limit::perMinute(10)->by('portal-login|'.$r->ip()));
        RateLimiter::for('password-reset', fn (Request $r) => [
            Limit::perMinutes(10, 3)->by('pw-mail|'.$r->ip()),
            Limit::perMinutes(10, 3)->by('pw-mail|'.strtolower((string) $r->input('email'))),
        ]);
        RateLimiter::for('password-reset-submit', fn (Request $r) => Limit::perMinute(10)->by('pw-reset|'.$r->ip()));
        RateLimiter::for('webmail', fn (Request $r) => Limit::perMinute(10)->by('webmail|'.($r->user()?->id ?: $r->ip())));
        RateLimiter::for('test-mail', fn (Request $r) => Limit::perMinute(5)->by('test-mail|'.$r->ip()));
    }
}
