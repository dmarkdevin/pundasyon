<?php

declare(strict_types=1);

use App\Enums\LoginEventType;
use App\Filament\Pages\Security;
use App\Models\LoginEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

use function Pest\Livewire\livewire;

function addSession(string $id, ?User $user, string $agent = 'Mozilla/5.0 (Windows NT 10.0) Chrome/130.0 Safari/537.36'): void
{
    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $user?->id,
        'ip_address' => '203.0.113.7',
        'user_agent' => $agent,
        'payload' => '',
        'last_activity' => now()->timestamp,
    ]);
}

beforeEach(function (): void {
    config(['session.driver' => 'database']);
});

it('keeps the page behind sign-in', function (): void {
    $this->get('/app/security')->assertRedirect('/app/login');
});

it('lists only your own devices and sign-ins', function (): void {
    $me = User::factory()->create();
    $someoneElse = User::factory()->create();
    addSession('mine', $me, 'Mozilla/5.0 (iPhone) Safari/604.1');
    addSession('theirs', $someoneElse, 'Mozilla/5.0 (X11; Linux) Firefox/130.0');
    LoginEvent::query()->create(['user_id' => $someoneElse->id, 'email' => 'their-secret@example.com', 'type' => LoginEventType::Succeeded]);

    $this->actingAs($me);

    livewire(Security::class)
        ->assertSee('Safari on iPhone')
        ->assertDontSee('Firefox on Linux')
        ->assertDontSee('their-secret@example.com');
});

it('needs the right password to log out other devices', function (): void {
    $me = User::factory()->create();
    addSession('other-device', $me);
    $this->actingAs($me);

    livewire(Security::class)
        ->callAction('logoutOtherDevices', ['password' => 'wrong'])
        ->assertHasActionErrors(['password']);

    expect(DB::table('sessions')->where('id', 'other-device')->exists())->toBeTrue();
});

it('logs out other devices and keeps this one', function (): void {
    $me = User::factory()->create();
    $someoneElse = User::factory()->create();
    addSession('other-device', $me);
    addSession('their-device', $someoneElse);
    $this->actingAs($me);

    livewire(Security::class)
        ->callAction('logoutOtherDevices', ['password' => 'password'])
        ->assertHasNoActionErrors();

    expect(DB::table('sessions')->where('id', 'other-device')->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('id', 'their-device')->exists())->toBeTrue();
    $this->assertAuthenticatedAs($me);
});
