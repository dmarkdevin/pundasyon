<?php

declare(strict_types=1);

use App\Enums\LoginEventType;
use App\Enums\SystemRole;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\RecentActivity;
use App\Filament\Widgets\SignUpsChart;
use App\Filament\Widgets\UserStats;
use App\Models\LoginEvent;
use App\Models\User;

use function Pest\Livewire\livewire;

it('shows people only the widgets they have permission for', function (): void {
    $this->actingAs(userWithRole(SystemRole::User));

    expect(UserStats::canView())->toBeFalse()
        ->and(SignUpsChart::canView())->toBeFalse()
        ->and(RecentActivity::canView())->toBeFalse();
    $this->get('/app')->assertOk();

    $this->actingAs(userWithRole(SystemRole::Admin));
    expect(UserStats::canView())->toBeTrue()
        ->and(RecentActivity::canView())->toBeTrue();
});

it('counts users and today\'s sign-ins', function (): void {
    $this->actingAs(userWithRole(SystemRole::Admin));
    User::factory()->count(3)->create();
    User::factory()->suspended()->create();
    LoginEvent::query()->create(['email' => 'a@example.com', 'type' => LoginEventType::Succeeded]);
    LoginEvent::query()->create(['email' => 'b@example.com', 'type' => LoginEventType::Failed]);

    livewire(UserStats::class)
        ->assertSee('Active users')
        ->assertSeeInOrder(['Active users', '4'])
        ->assertSee('1 failed or blocked');
});

it('counts sign-ups for each of the last 30 days', function (): void {
    $this->actingAs(userWithRole(SystemRole::Admin));
    User::factory()->create(['created_at' => now()->subDays(2)]);
    User::factory()->count(2)->create(['created_at' => now()->subDays(2)]);
    User::factory()->create(['created_at' => now()->subDays(40)]);

    $counts = (new SignUpsChart)->countsPerDay();

    expect($counts)->toHaveCount(30)
        ->and($counts[now()->subDays(2)->toDateString()])->toBe(3)
        ->and(array_sum($counts))->toBe(4); // 3 plus the signed-in admin, today
});

it('renders the dashboard with every widget', function (): void {
    $this->actingAs(userWithRole(SystemRole::SuperAdmin));

    // Widgets load after the page, so each one is rendered on its own.
    livewire(Dashboard::class)->assertOk();
    livewire(UserStats::class)->assertOk();
    livewire(SignUpsChart::class)->assertSee('Sign-ups, last 30 days');
    livewire(RecentActivity::class)->assertSee('Recent activity');
});

it('counts sign-ups by the day people see, not the UTC day', function (): void {
    config(['app.display_timezone' => 'Asia/Manila']);
    $this->actingAs(userWithRole(SystemRole::Admin));
    // 22:30 UTC yesterday is 06:30 today in Manila.
    $this->travelTo(now('UTC')->setTime(12, 0));
    User::factory()->create(['created_at' => now('UTC')->subDay()->setTime(22, 30)]);

    $counts = (new SignUpsChart)->countsPerDay();

    expect($counts[now('Asia/Manila')->toDateString()])->toBe(2); // the admin, and the late-night sign-up
});
