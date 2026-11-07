<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Headers that tell browsers to block framing, type guessing and unexpected scripts.
 * The policies themselves live in config/security.php.
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        // Don't advertise the PHP version. On a server, also set expose_php = Off.
        $headers->remove('X-Powered-By');
        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $headers->set('Permissions-Policy', (string) config('security.permissions_policy'));

        // Leave a policy alone if a page set its own.
        if (! $headers->has('Content-Security-Policy')) {
            $policy = $request->is('docs/api', 'docs/api/*') ? 'security.docs_content_security_policy' : 'security.content_security_policy';
            $headers->set('Content-Security-Policy', (string) config($policy));
        }

        // Browsers only honour this over HTTPS, and it's hard to undo, so production only.
        if ($request->isSecure() && app()->isProduction()) {
            $headers->set('Strict-Transport-Security', 'max-age='.(int) config('security.hsts_max_age').'; includeSubDomains');
        }

        return $response;
    }
}
