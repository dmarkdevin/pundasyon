<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Models\User;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Laravel\Sanctum\PersonalAccessToken;

function apiLogin(array $data = []): Illuminate\Testing\TestResponse
{
    return test()->postJson('/api/v1/auth/login', [...['password' => 'password', 'device_name' => 'Test'], ...$data]);
}

it('signs in and returns a token with the person\'s permissions', function (): void {
    $admin = userWithRole(SystemRole::Admin);

    $response = apiLogin(['email' => $admin->email])
        ->assertCreated()
        ->assertJsonStructure(['data' => ['token', 'token_type', 'name', 'abilities', 'expires_at', 'user' => ['id', 'email', 'roles']]])
        ->assertJsonPath('data.token_type', 'Bearer');

    expect($response->json('data.abilities'))->toContain('users.view')->not->toContain('users.impersonate');
});

it('limits a token to the abilities asked for, never beyond the person\'s own', function (): void {
    $admin = userWithRole(SystemRole::Admin);

    apiLogin(['email' => $admin->email, 'abilities' => ['users.view', 'users.impersonate'], 'expires_in_days' => 30])
        ->assertCreated()
        ->assertJsonPath('data.abilities', ['users.view']);

    expect(PersonalAccessToken::query()->sole()->expires_at?->isFuture())->toBeTrue();
});

it('refuses wrong passwords in the standard error shape', function (): void {
    $user = User::factory()->create();

    apiLogin(['email' => $user->email, 'password' => 'wrong'])
        ->assertUnprocessable()
        ->assertExactJson(['message' => 'The given data was invalid.', 'code' => 'validation_failed', 'errors' => ['email' => ['These credentials do not match our records.']]]);
});

it('locks the account after repeated failures, like the web sign-in', function (): void {
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        apiLogin(['email' => $user->email, 'password' => 'wrong']);
    }

    // Wait out the per-minute limit, so only the account lockout is left.
    $this->travel(61)->seconds();

    apiLogin(['email' => $user->email])->assertUnprocessable()->assertJsonPath('errors.email.0', fn (string $message) => str_contains($message, 'Too many failed sign-ins'));
});

it('limits sign-in attempts per minute', function (): void {
    foreach (range(1, 5) as $attempt) {
        apiLogin(['email' => 'nobody@example.com']);
    }

    apiLogin(['email' => 'nobody@example.com'])->assertTooManyRequests()->assertJsonPath('code', 'too_many_requests');
});

it('asks for the two-factor code when it is turned on', function (): void {
    $user = User::factory()->create();
    $user->saveAppAuthenticationSecret(app(AppAuthentication::class)->generateSecret());

    apiLogin(['email' => $user->email])->assertUnprocessable()->assertJsonValidationErrors('code');
    apiLogin(['email' => $user->email, 'code' => '000000'])->assertUnprocessable()->assertJsonValidationErrors('code');
    apiLogin(['email' => $user->email, 'code' => AppAuthentication::make()->getCurrentCode($user)])->assertCreated();
});

it('refuses suspended and unconfirmed accounts', function (User $user): void {
    apiLogin(['email' => $user->email])->assertUnprocessable();

    expect(PersonalAccessToken::query()->count())->toBe(0);
})->with([
    'suspended' => fn () => User::factory()->suspended()->create(),
    'unconfirmed' => fn () => User::factory()->unverified()->create(),
]);

it('returns the signed-in user, without secrets', function (): void {
    $user = User::factory()->create();
    $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

    $json = asApiUser($user)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.email', $user->email)->json('data');

    expect(array_keys($json))->not->toContain('password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes');
});

it('answers 401 in the standard shape without a token', function (): void {
    $this->getJson('/api/v1/auth/me')->assertUnauthorized()->assertExactJson(['message' => 'Send a valid API token.', 'code' => 'unauthenticated']);
});

it('ignores a browser session: the API only takes tokens', function (): void {
    $this->actingAs(User::factory()->create(), 'web')->getJson('/api/v1/auth/me')->assertUnauthorized();
});

it('revokes the token on sign-out', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();
    app('auth')->forgetGuards();

    $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
});

it('stops tokens working once the account is suspended', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $user->forceFill(['status' => 'suspended'])->save();

    $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized()->assertJsonPath('message', 'This account is suspended.');
});

it('deletes tokens when an account is suspended', function (): void {
    $user = User::factory()->create();
    $user->createToken('test');

    app(App\Actions\Users\SuspendUser::class)($user);

    expect($user->tokens()->count())->toBe(0);
});
