<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\LoginEvent;
use App\Models\User;
use App\Support\Auth\DeviceName;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Where people see which devices are signed in to their account and their recent sign-ins.
 */
final class Security extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Sign-ins and devices';

    protected static ?string $slug = 'security';

    protected string $view = 'filament.pages.security';

    /**
     * Devices with a session on this account, newest first. Needs the database session driver.
     *
     * @return Collection<int, array{id: string, device: string, ip: ?string, last_active: Carbon, current: bool}>
     */
    public function getSessions(): Collection
    {
        if (config('session.driver') !== 'database') {
            return collect();
        }

        return DB::table((string) config('session.table', 'sessions'))
            ->where('user_id', $this->user()->getKey())
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn (object $session): array => [
                'id' => (string) $session->id,
                'device' => DeviceName::from(is_string($session->user_agent) ? $session->user_agent : null),
                'ip' => is_string($session->ip_address) ? $session->ip_address : null,
                'last_active' => Carbon::createFromTimestamp((int) $session->last_activity, config('app.timezone')),
                'current' => $session->id === session()->getId(),
            ])
            ->values();
    }

    /**
     * @return Collection<int, LoginEvent>
     */
    public function getLoginEvents(): Collection
    {
        return LoginEvent::query()
            ->whereBelongsTo($this->user())
            ->latest('created_at')
            ->latest('id')
            ->limit(20)
            ->get();
    }

    public function deviceName(?string $userAgent): string
    {
        return DeviceName::from($userAgent);
    }

    /**
     * Asks for the password, so someone at an unattended computer can't lock the owner out.
     */
    public function logoutOtherDevicesAction(): Action
    {
        return Action::make('logoutOtherDevices')
            ->label('Log out other devices')
            ->color('danger')
            ->modalDescription('Everywhere else this account is signed in will be signed out. This device stays signed in.')
            ->schema([
                TextInput::make('password')
                    ->label('Your password')
                    ->password()
                    ->required()
                    ->currentPassword(),
            ])
            ->action(function (array $data): void {
                Auth::logoutOtherDevices($data['password']);

                if (config('session.driver') === 'database') {
                    DB::table((string) config('session.table', 'sessions'))
                        ->where('user_id', $this->user()->getKey())
                        ->where('id', '!=', session()->getId())
                        ->delete();
                }

                Notification::make()->title('Other devices were logged out.')->success()->send();
            });
    }

    private function user(): User
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
