<?php

declare(strict_types=1);

use App\Filament\Pages\Auth\Register;
use App\Settings\AuthSettings;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

use function Pest\Livewire\livewire;

function signUpWith(string $password): Livewire\Features\SupportTesting\Testable
{
    return livewire(Register::class)
        ->fillForm([
            'name' => 'Ana Reyes',
            'email' => 'ana@example.com',
            'password' => $password,
            'passwordConfirmation' => $password,
        ])
        ->call('register');
}

it('refuses short passwords', function (): void {
    signUpWith('Short-1a')->assertHasFormErrors(['password']);
});

it('refuses passwords without mixed case or numbers', function (string $password): void {
    signUpWith($password)->assertHasFormErrors(['password']);
})->with(['all-lowercase-123', 'NoNumbersAtAllHere']);

it('applies a changed rule straight away', function (): void {
    $settings = app(AuthSettings::class);
    $settings->password_requires_symbols = true;
    $settings->save();

    // Checked first: a successful sign-up signs the person in and closes the page.
    signUpWith('Correcthorse42X')->assertHasFormErrors(['password']);
    signUpWith('Correct-horse-42')->assertHasNoFormErrors();
});

it('refuses passwords found in data breaches', function (): void {
    $password = 'Correct-horse-42';
    $hash = strtoupper(sha1($password));

    // Replaces the default "not found" answer from TestCase. Have I Been Pwned only
    // ever receives the first 5 characters of the hash.
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake(['api.pwnedpasswords.com/range/'.substr($hash, 0, 5) => Http::response(substr($hash, 5).':3021')]);

    signUpWith($password)->assertHasFormErrors(['password']);
});
