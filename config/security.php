<?php

declare(strict_types=1);

return [

    /*
    | Redirect plain HTTP to HTTPS and send Strict-Transport-Security.
    | Only applies in production, so local development keeps working on http://.
    */
    'force_https' => (bool) env('SECURITY_FORCE_HTTPS', true),

    'hsts_max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31536000),

    /*
    | Content-Security-Policy sent with every page. Filament needs inline styles and
    | Alpine.js needs 'unsafe-eval', so those can't be removed without breaking the panel.
    | Add hosts here when your app loads images, scripts or fonts from elsewhere.
    */
    'content_security_policy' => env('SECURITY_CSP', implode('; ', [
        "default-src 'self'",
        "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
        "style-src 'self' 'unsafe-inline'",
        "img-src 'self' data: blob:",
        "font-src 'self' data:",
        "connect-src 'self'",
        // Filament's file uploads and image editor run helpers from blob: URLs.
        "worker-src 'self' blob:",
        "frame-ancestors 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        "object-src 'none'",
    ])),

    'permissions_policy' => env('SECURITY_PERMISSIONS_POLICY', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()'),

    // Requests per minute for each API token (or each address, before signing in).
    'api_rate_limit' => (int) env('API_RATE_LIMIT', 60),

    // API sign-in attempts per minute for each email and address.
    'api_login_rate_limit' => (int) env('API_LOGIN_RATE_LIMIT', 5),

    // API sign-in attempts per minute from one address, whatever the email.
    'api_login_rate_limit_per_address' => (int) env('API_LOGIN_RATE_LIMIT_PER_ADDRESS', 20),

    /*
    | The API docs page loads its viewer (Stoplight Elements) from unpkg.com, so it
    | gets its own policy. Every other page keeps the strict one above.
    */
    'docs_content_security_policy' => implode('; ', [
        "default-src 'self'",
        // Only the pinned viewer package, which is also checked against integrity hashes.
        "script-src 'self' 'unsafe-inline' https://unpkg.com/@stoplight/elements@8.4.2/",
        "style-src 'self' 'unsafe-inline' https://unpkg.com/@stoplight/elements@8.4.2/",
        "img-src 'self' data: https:",
        "font-src 'self' data:",
        "connect-src 'self'",
        "frame-ancestors 'none'",
        "base-uri 'self'",
        "object-src 'none'",
    ]),

    // Proxy or load balancer addresses (comma-separated), or * when every request comes through one you control.
    'trusted_proxies' => env('TRUSTED_PROXIES'),

    // Sign-in history older than this many days is deleted by the scheduler.
    'login_history_days' => (int) env('SECURITY_LOGIN_HISTORY_DAYS', 180),
];
