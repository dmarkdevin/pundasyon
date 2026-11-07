<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Enums\UserStatus;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\Register;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Role;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\DB;

use function Pest\Livewire\livewire;

use Spatie\Activitylog\Models\Activity;

it('keeps people without users.view out of the user screens', function (): void {
    $this->actingAs(userWithRole(SystemRole::User));

    $this->get('/app/users')->assertForbidden();
    $this->get('/app/users/create')->assertForbidden();
});

it('lets admins list and search users', function (): void {
    $this->actingAs(userWithRole(SystemRole::Admin));
    $ana = User::factory()->create(['name' => 'Ana Reyes']);
    $ben = User::factory()->create(['name' => 'Ben Cruz']);

    livewire(ListUsers::class)
        ->assertCanSeeTableRecords([$ana, $ben])
        ->searchTable('Ana')
        ->assertCanSeeTableRecords([$ana])
        ->assertCanNotSeeTableRecords([$ben]);
});

it('lets admins create a confirmed user with a role', function (): void {
    $this->actingAs(userWithRole(SystemRole::Admin));

    livewire(CreateUser::class)
        ->fillForm([
            'name' => 'Ana Reyes',
            'email' => 'ana@example.com',
            'password' => 'Correct-horse-42',
            'passwordConfirmation' => 'Correct-horse-42',
            'roles' => [Role::findByName('user')->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $ana = User::query()->where('email', 'ana@example.com')->sole();
    expect($ana->hasVerifiedEmail())->toBeTrue()
        ->and($ana->hasRole('user'))->toBeTrue();
});

it('does not let admins hand out the super-admin role', function (): void {
    $this->actingAs(userWithRole(SystemRole::Admin));

    livewire(CreateUser::class)
        ->fillForm([
            'name' => 'Ana Reyes',
            'email' => 'ana@example.com',
            'password' => 'Correct-horse-42',
            'passwordConfirmation' => 'Correct-horse-42',
            'roles' => [Role::findByName('super-admin')->id],
        ])
        ->call('create')
        ->assertHasFormErrors(['roles.0']);

    expect(User::query()->where('email', 'ana@example.com')->exists())->toBeFalse();
});

it('does not let admins hand out a permission they lack', function (): void {
    $this->actingAs(userWithRole(SystemRole::Admin));
    $target = User::factory()->create();

    livewire(EditUser::class, ['record' => $target->getRouteKey()])
        ->fillForm(['permissions' => [App\Models\Permission::findByName('users.impersonate')->id]])
        ->call('save')
        ->assertHasFormErrors(['permissions.0']);

    expect($target->fresh()->hasDirectPermission('users.impersonate'))->toBeFalse();
});

it('does not let anyone change an account with more access than their own', function (): void {
    seedAccess();
    $auditor = Role::create(['name' => 'auditor', 'guard_name' => 'web'])->givePermissionTo('users.impersonate');
    $target = User::factory()->create();
    $target->assignRole($auditor);
    $this->actingAs(userWithRole(SystemRole::Admin));

    // Changing their password or email would hand the admin a permission they don't have.
    $this->get("/app/users/{$target->id}/edit")->assertForbidden();
});

it('does not let people change their own roles', function (): void {
    $admin = userWithRole(SystemRole::Admin);
    $this->actingAs($admin);

    livewire(EditUser::class, ['record' => $admin->getRouteKey()])
        ->assertFormFieldDisabled('roles')
        ->set('data.roles', [Role::findByName('super-admin')->id])
        ->call('save');

    expect($admin->fresh()->hasRole('super-admin'))->toBeFalse();
});

it('keeps admins away from super admins', function (): void {
    $superAdmin = userWithRole(SystemRole::SuperAdmin);
    $this->actingAs(userWithRole(SystemRole::Admin));

    $this->get("/app/users/{$superAdmin->id}/edit")->assertForbidden();
});

it('stops anyone deleting or suspending themselves, super admins included', function (): void {
    $superAdmin = userWithRole(SystemRole::SuperAdmin);
    $this->actingAs($superAdmin);

    livewire(EditUser::class, ['record' => $superAdmin->getRouteKey()])
        ->assertActionHidden(DeleteAction::class)
        ->assertActionHidden('suspend');
});

it('skips yourself and super admins when bulk deleting', function (): void {
    $superAdmin = userWithRole(SystemRole::SuperAdmin);
    $admin = userWithRole(SystemRole::Admin);
    $member = User::factory()->create();
    $this->actingAs($admin);

    livewire(ListUsers::class)
        ->selectTableRecords([$admin->id, $superAdmin->id, $member->id])
        ->callAction(TestAction::make('delete')->table()->bulk());

    expect($member->fresh()->trashed())->toBeTrue()
        ->and($admin->fresh()->trashed())->toBeFalse()
        ->and($superAdmin->fresh()->trashed())->toBeFalse();
});

it('soft deletes and restores users', function (): void {
    $this->actingAs(userWithRole(SystemRole::Admin));
    $target = User::factory()->create();

    livewire(ListUsers::class)->callAction(TestAction::make('delete')->table($target));
    expect($target->fresh()->trashed())->toBeTrue();

    livewire(ListUsers::class)
        ->filterTable('trashed', true)
        ->callAction(TestAction::make('restore')->table($target));
    expect($target->fresh()->trashed())->toBeFalse();
});

it('suspends someone, signs them out everywhere and logs it', function (): void {
    config(['session.driver' => 'database']);
    $target = User::factory()->create();
    DB::table('sessions')->insert(['id' => 'their-phone', 'user_id' => $target->id, 'payload' => '', 'last_activity' => now()->timestamp]);
    $this->actingAs(userWithRole(SystemRole::Admin));

    livewire(ListUsers::class)->callAction(TestAction::make('suspend')->table($target));

    expect($target->fresh()->status)->toBe(UserStatus::Suspended)
        ->and(DB::table('sessions')->where('user_id', $target->id)->exists())->toBeFalse()
        ->and(Activity::query()->where('event', 'suspended')->where('subject_id', $target->id)->exists())->toBeTrue();

    livewire(ListUsers::class)->callAction(TestAction::make('activate')->table($target));
    expect($target->fresh()->status)->toBe(UserStatus::Active);
});

it('refuses sign-in to suspended and deleted accounts', function (User $user): void {
    livewire(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    $this->assertGuest();
})->with([
    'suspended' => fn () => User::factory()->suspended()->create(),
    'deleted' => fn () => tap(User::factory()->create())->delete(),
]);

it('tells a suspended person why only when their password is right', function (): void {
    $user = User::factory()->suspended()->create();

    $right = livewire(Login::class)->fillForm(['email' => $user->email, 'password' => 'password'])->call('authenticate');
    $wrong = livewire(Login::class)->fillForm(['email' => $user->email, 'password' => 'not-it'])->call('authenticate');

    expect(implode(' ', $right->errors()->get('data.email')))->toContain('suspended')
        ->and(implode(' ', $wrong->errors()->get('data.email')))->not->toContain('suspended');
    $this->assertGuest();
});

it('signs out someone suspended while signed in', function (): void {
    $user = User::factory()->suspended()->create();

    $this->actingAs($user)->get('/app')->assertRedirect('/app/login');
    $this->assertGuest();
    livewire(Login::class)->assertNotified('Your account is suspended.');
});

it('gives people who sign up the user role', function (): void {
    seedAccess();

    livewire(Register::class)
        ->fillForm(['name' => 'Ana', 'email' => 'ana@example.com', 'password' => 'Correct-horse-42', 'passwordConfirmation' => 'Correct-horse-42'])
        ->call('register');

    expect(User::query()->where('email', 'ana@example.com')->sole()->hasRole('user'))->toBeTrue();
});

it('lets super admins impersonate, and logs it', function (): void {
    $superAdmin = userWithRole(SystemRole::SuperAdmin);
    $target = User::factory()->create();
    $this->actingAs($superAdmin);

    livewire(ListUsers::class)->callAction(TestAction::make('impersonate')->table($target));

    $this->assertAuthenticatedAs($target);
    expect(Activity::query()->where('event', 'impersonation-started')->sole())
        ->causer_id->toBe($superAdmin->id)
        ->subject_id->toBe($target->id);
});

it('does not let admins impersonate, or anyone impersonate a super admin', function (): void {
    $otherSuperAdmin = userWithRole(SystemRole::SuperAdmin);
    $target = User::factory()->create();

    $this->actingAs(userWithRole(SystemRole::Admin));
    livewire(ListUsers::class)->assertActionHidden(TestAction::make('impersonate')->table($target));

    $this->actingAs(userWithRole(SystemRole::SuperAdmin));
    livewire(ListUsers::class)->assertActionHidden(TestAction::make('impersonate')->table($otherSuperAdmin));
});

it('does not run more queries for a longer user list', function (): void {
    $this->actingAs(userWithRole(SystemRole::Admin));
    $count = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        livewire(ListUsers::class);

        return count(DB::getQueryLog());
    };

    User::factory()->count(2)->create()->each->assignRole('user');
    $count(); // The first render also loads the permission cache.
    $few = $count();
    User::factory()->count(10)->create()->each->assignRole('user');

    expect($count())->toBe($few);
});
