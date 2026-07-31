<?php

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            RateLimiter::for('api', function (Request $request): Limit {
                return Limit::perMinute((int) config('security.rate_limits.api_per_minute', 120))
                    ->by((string) ($request->user()?->id ?: $request->ip()));
            });

            RateLimiter::for('auth.login', function (Request $request): Limit {
                return Limit::perMinute((int) config('security.rate_limits.login_per_minute', 10))
                    ->by(strtolower((string) $request->input('email')).'|'.$request->ip());
            });

            RateLimiter::for('auth.mfa', function (Request $request): Limit {
                return Limit::perMinute((int) config('security.rate_limits.mfa_per_minute', 8))
                    ->by(hash('sha256', (string) $request->input('challenge_token')).'|'.$request->ip());
            });
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);
        $middleware->alias([
            'permission' => CheckPermission::class,
        ]);
        $middleware->redirectGuestsTo(fn (Request $request): ?string => $request->is('api/*') ? null : '/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
