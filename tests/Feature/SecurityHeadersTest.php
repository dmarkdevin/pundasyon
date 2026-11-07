<?php

declare(strict_types=1);

use App\Models\User;
use Filament\Facades\Filament;

it('sends security headers on pages', function (string $path): void {
    $this->get($path)
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeaderMissing('Strict-Transport-Security')
        ->assertHeader('Content-Security-Policy');
})->with(['/app/login', '/up']);

it('blocks the app from being framed by other sites', function (): void {
    expect($this->get('/app/login')->headers->get('Content-Security-Policy'))->toContain("frame-ancestors 'none'");
});

it('marks the session cookie secure in production', function (): void {
    app()->detectEnvironment(fn (): string => 'production');
    config(['session.secure' => null]);
    (new App\Providers\AppServiceProvider(app()))->boot();

    expect(config('session.secure'))->toBeTrue();
});

it('lets file uploads run their helpers', function (): void {
    expect($this->get('/app/login')->headers->get('Content-Security-Policy'))->toContain("worker-src 'self' blob:");
});

it('redirects plain HTTP to HTTPS in production', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    $this->get('http://localhost/app/login')->assertRedirect('https://localhost/app/login')->assertStatus(301);
});

it('sends HSTS over HTTPS in production only', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    $this->get('https://localhost/app/login')->assertHeader('Strict-Transport-Security');
});

it('draws avatars locally instead of sending names to another site', function (): void {
    $user = User::factory()->create(['name' => 'Ana Reyes']);

    expect(Filament::getUserAvatarUrl($user))->toStartWith('data:image/svg+xml');
});

it('trusts the configured proxy for the original scheme', function (): void {
    config(['security.trusted_proxies' => '10.0.0.1']);
    (new App\Providers\AppServiceProvider(app()))->boot();
    app()->detectEnvironment(fn (): string => 'production');

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
        ->withHeader('X-Forwarded-Proto', 'https')
        ->get('http://localhost/app/login')
        ->assertOk()
        ->assertHeader('Strict-Transport-Security');
});
