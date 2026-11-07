<?php

declare(strict_types=1);

use App\Http\Middleware\RedirectToHttps;
use App\Http\Middleware\SecurityHeaders;
use App\Support\ApiErrors;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trusted proxies are set in AppServiceProvider, from config/security.php.

        // After Laravel's TrustProxies, so a request that reached the proxy over HTTPS counts as secure.
        $middleware->append(RedirectToHttps::class);
        $middleware->append(SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // One error shape for the whole API.
        $exceptions->render(fn (Throwable $exception, Request $request) => ApiErrors::render($exception, $request));
    })->create();
