<?php

declare(strict_types=1);

use App\Filament\Pages\Auth\EditProfile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

beforeEach(fn () => Storage::fake('local'));

it('stores a profile photo privately and shows it through a signed link', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    livewire(EditProfile::class)
        ->fillForm(['avatar_path' => UploadedFile::fake()->image('me.png', 200, 200)])
        ->call('save')
        ->assertHasNoFormErrors();

    $path = (string) $user->fresh()->avatar_path;
    expect($path)->toStartWith('avatars/')
        ->and(Storage::disk('local')->exists($path))->toBeTrue()
        ->and($user->fresh()->getFilamentAvatarUrl())->not->toBeNull();
});

it('deletes the old photo when it is replaced, and with the account', function (): void {
    Storage::disk('local')->put('avatars/old.png', 'old');
    Storage::disk('local')->put('avatars/new.png', 'new');
    $user = User::factory()->create(['avatar_path' => 'avatars/old.png']);

    $user->update(['avatar_path' => 'avatars/new.png']);
    expect(Storage::disk('local')->exists('avatars/old.png'))->toBeFalse()
        ->and(Storage::disk('local')->exists('avatars/new.png'))->toBeTrue();

    $user->forceDelete();
    expect(Storage::disk('local')->exists('avatars/new.png'))->toBeFalse();
});

it('serves private files away from the public /storage folder', function (): void {
    expect(parse_url((string) config('filesystems.disks.local.url'), PHP_URL_PATH))->toBe('/private-files')
        ->and(Route::has('storage.local'))->toBeTrue()
        ->and(route('storage.local', ['path' => 'avatars/me.png'], false))->toBe('/private-files/avatars/me.png');
});

it('refuses files that are not images', function (): void {
    $this->actingAs(User::factory()->create());

    livewire(EditProfile::class)
        ->fillForm(['avatar_path' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')])
        ->call('save')
        ->assertHasFormErrors(['avatar_path']);
});

it('never serves a path outside the avatars folder', function (): void {
    $user = User::factory()->create(['avatar_path' => '../.env']);

    expect($user->getFilamentAvatarUrl())->toBeNull()
        ->and(Filament::getUserAvatarUrl($user))->toStartWith('data:image/svg+xml');
});
