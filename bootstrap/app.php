<?php

use App\Http\Middleware\AddContentSecurityPolicy;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Behind a trusted proxy the host can come from X-Forwarded-Host, so only accept the
        // app's own host and Railway's health checker. Without trusted proxies, no restriction.
        $middleware->trustHosts(at: function (): array {
            $host = parse_url((string) config('app.url'), PHP_URL_HOST);

            if (blank(config('app.trusted_proxies')) || ! is_string($host)) {
                return [];
            }

            return ['^'.preg_quote($host).'$', '^healthcheck\.railway\.app$'];
        }, subdomains: false);

        $middleware->web(append: [
            AddContentSecurityPolicy::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
