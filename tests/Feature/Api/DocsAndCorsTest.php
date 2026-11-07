<?php

declare(strict_types=1);

use App\Enums\SystemRole;

it('shows the API docs to admins only outside local development', function (): void {
    $this->get('/docs/api')->assertForbidden();

    $this->actingAs(userWithRole(SystemRole::User))->get('/docs/api')->assertForbidden();
    $this->actingAs(userWithRole(SystemRole::Admin))->get('/docs/api')->assertOk();
});

it('documents the v1 endpoints and bearer tokens', function (): void {
    $spec = $this->actingAs(userWithRole(SystemRole::Admin))->getJson('/docs/api.json')->assertOk()->json();

    expect(array_keys($spec['paths']))->toContain('/v1/users', '/v1/roles', '/v1/auth/login')
        ->and(json_encode($spec['components']['securitySchemes'] ?? []))->toContain('bearer');
});

it('lets the docs page load its viewer while everything else stays locked down', function (): void {
    $docs = $this->actingAs(userWithRole(SystemRole::Admin))->get('/docs/api')->headers->get('Content-Security-Policy');
    $app = $this->get('/app/login')->headers->get('Content-Security-Policy');

    expect($docs)->toContain('https://unpkg.com/@stoplight/elements@8.4.2/')->and($app)->not->toContain('unpkg.com');
});

it('loads the docs viewer with integrity hashes', function (): void {
    $html = $this->actingAs(userWithRole(SystemRole::Admin))->get('/docs/api')->getContent();

    expect(substr_count((string) $html, 'integrity="sha384-'))->toBe(2);
});

it('allows browser calls only from configured sites', function (): void {
    config(['cors.allowed_origins' => ['https://app.example.com']]);

    $this->call('OPTIONS', '/api/v1/auth/me', server: ['HTTP_ORIGIN' => 'https://app.example.com', 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET'])
        ->assertHeader('Access-Control-Allow-Origin', 'https://app.example.com');

    // With one allowed site the header always names that site, so a browser on any other site refuses the answer.
    $other = $this->call('OPTIONS', '/api/v1/auth/me', server: ['HTTP_ORIGIN' => 'https://evil.example.com', 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET']);
    expect($other->headers->get('Access-Control-Allow-Origin'))->not->toBe('https://evil.example.com');
});
