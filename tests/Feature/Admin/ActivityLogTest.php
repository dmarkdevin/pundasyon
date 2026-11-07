<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Filament\Pages\Auth\Login;
use App\Filament\Resources\Activities\Pages\ListActivities;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Auth;

use function Pest\Livewire\livewire;

use Spatie\Activitylog\Models\Activity;

it('records changes to users with before and after, never the password', function (): void {
    $user = User::factory()->create(['name' => 'Old Name']);

    $user->update(['name' => 'New Name', 'password' => 'Another-password-1']);

    $entry = Activity::query()->where('log_name', 'users')->where('event', 'updated')->sole();
    expect($entry->description)->toBe('Updated user')
        ->and($entry->attribute_changes->get('old'))->toBe(['name' => 'Old Name'])
        ->and($entry->attribute_changes->get('attributes'))->toBe(['name' => 'New Name'])
        ->and(Activity::query()->where('event', 'password-changed')->exists())->toBeTrue()
        ->and(Activity::query()->get()->toJson())->not->toContain('Another-password-1')->not->toContain('$2y$');
});

it('records two-factor being turned on and off', function (): void {
    $user = User::factory()->create();

    $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    $user->saveAppAuthenticationSecret(null);

    expect(Activity::query()->where('log_name', 'auth')->pluck('event')->all())->toContain('two-factor-enabled', 'two-factor-disabled')
        ->and(Activity::query()->get()->toJson())->not->toContain('JBSWY3DPEHPK3PXP');
});

it('records sign-ins, failed sign-ins and sign-outs', function (): void {
    $user = User::factory()->create();

    livewire(Login::class)->fillForm(['email' => $user->email, 'password' => 'wrong'])->call('authenticate');
    livewire(Login::class)->fillForm(['email' => $user->email, 'password' => 'password'])->call('authenticate');
    Auth::logout();

    expect(Activity::query()->where('log_name', 'auth')->pluck('event')->all())->toContain('sign-in-failed', 'signed-in', 'signed-out');
});

it('records roles given and taken away', function (): void {
    $this->actingAs(userWithRole(SystemRole::SuperAdmin));
    $target = User::factory()->create();
    $target->assignRole('user');

    livewire(EditUser::class, ['record' => $target->getRouteKey()])
        ->fillForm(['roles' => [Role::findByName('admin')->id]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Activity::query()->where('event', 'access-changed')->sole()->properties->all())
        ->toBe(['added' => ['admin'], 'removed' => ['user']]);
});

it('records permissions added to a role', function (): void {
    $this->actingAs(userWithRole(SystemRole::SuperAdmin));
    $role = Role::create(['name' => 'support-agent', 'guard_name' => 'web']);

    livewire(EditRole::class, ['record' => $role->getRouteKey()])
        ->fillForm(['permissions' => [Permission::findByName('users.view')->id]])
        ->call('save');

    expect(Activity::query()->where('event', 'access-changed')->sole()->properties->get('added'))->toBe(['users.view']);
});

it('keeps the log away from people without activity.view', function (): void {
    $this->actingAs(userWithRole(SystemRole::User));

    $this->get('/app/activity')->assertForbidden();
});

it('lists, filters and shows entries', function (): void {
    $admin = userWithRole(SystemRole::Admin);
    $this->actingAs($admin);
    $user = User::factory()->create(['name' => 'Old Name']);
    $user->update(['name' => 'New Name']);
    activity('auth')->event('signed-in')->log('Signed in');
    $userEntry = Activity::query()->where('log_name', 'users')->where('event', 'updated')->sole();

    livewire(ListActivities::class)
        ->assertCanSeeTableRecords([$userEntry])
        ->filterTable('log_name', 'auth')
        ->assertCanNotSeeTableRecords([$userEntry]);

    livewire(ListActivities::class)
        ->mountAction(TestAction::make('view')->table($userEntry))
        ->assertMountedActionModalSee(['Old Name', 'New Name']);
});

it('cannot be changed from the app', function (): void {
    $this->actingAs(userWithRole(SystemRole::SuperAdmin));
    $entry = activity()->log('Something happened');

    expect(auth()->user()->can('update', $entry))->toBeTrue() // super admins pass every check...
        ->and(Route::has('filament.app.resources.activity.edit'))->toBeFalse() // ...but there is no screen to do it
        ->and(Route::has('filament.app.resources.activity.create'))->toBeFalse();

    $this->actingAs(userWithRole(SystemRole::Admin));
    expect(auth()->user()->can('update', $entry))->toBeFalse()
        ->and(auth()->user()->can('delete', $entry))->toBeFalse();
});
