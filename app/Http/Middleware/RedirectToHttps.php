<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends plain HTTP requests to HTTPS in production. Behind a load balancer,
 * set TRUSTED_PROXIES so the original scheme is recognised.
 */
final class RedirectToHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->isProduction() && config('security.force_https') && ! $request->isSecure()) {
            return redirect()->secure($request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
