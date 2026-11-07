@php
    // The database may be unavailable during maintenance, so every read is optional.
    $settings = rescue(fn () => app(\App\Settings\GeneralSettings::class), null, false);
    $appName = $settings?->app_name ?: config('app.name');
    $message = $settings?->maintenance_message;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $appName }} is down for maintenance</title>
    <style>
        :root { color-scheme: light dark; font-family: system-ui, sans-serif; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #fafafa; color: #18181b; }
        main { max-width: 32rem; padding: 2rem; text-align: center; }
        h1 { font-size: 1.5rem; margin: 0 0 .75rem; }
        p { line-height: 1.6; color: #52525b; white-space: pre-line; }
        @media (prefers-color-scheme: dark) { body { background: #09090b; color: #fafafa; } p { color: #a1a1aa; } }
    </style>
</head>
<body>
    <main>
        <h1>{{ $appName }} is down for maintenance</h1>
        <p>{{ $message ?: 'We\'ll be back shortly. Thanks for your patience.' }}</p>
    </main>
</body>
</html>
