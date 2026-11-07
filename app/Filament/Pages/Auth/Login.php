<?php

declare(strict_types=1);

namespace App\Filament\Pages\Auth;

use App\Enums\LoginEventType;
use App\Enums\UserStatus;
use App\Http\Middleware\EnsureUserIsActive;
use App\Listeners\RecordLoginEvents;
use App\Models\User;
use App\Settings\AuthSettings;
use App\Support\Auth\AccountLockout;
use App\Support\Auth\DummyHash;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Filament's sign-in page, plus the account lockout and a sign-up link that follows the setting.
 */
final class Login extends BaseLogin
{
    public function mount(): void
    {
        parent::mount();

        if (session()->pull(EnsureUserIsActive::SUSPENDED_FLASH)) {
            Notification::make()
                ->title('Your account is suspended.')
                ->body('Contact an administrator if you think this is a mistake.')
                ->danger()
                ->persistent()
                ->send();
        }
    }

    public function authenticate(): ?LoginResponse
    {
        $email = $this->data['email'] ?? null;
        $lockout = app(AccountLockout::class);

        if (is_string($email) && $email !== '' && $lockout->isLocked($email, request()->ip())) {
            app(RecordLoginEvents::class)->record(LoginEventType::LockedOut, $email);

            throw ValidationException::withMessages([
                'data.email' => __('Too many failed sign-ins for this account. Try again in :minutes minutes, or reset your password.', [
                    'minutes' => max(1, (int) ceil($lockout->secondsLeft($email, request()->ip()) / 60)),
                ]),
            ]);
        }

        try {
            return parent::authenticate();
        } catch (ValidationException $exception) {
            $this->explainSuspension(is_string($email) ? $email : '', $this->data['password'] ?? null);

            throw $exception;
        }
    }

    /**
     * A suspended person with the right password is told why, instead of "wrong password".
     * The password is always hashed, account or not, so timing doesn't reveal which emails exist.
     */
    private function explainSuspension(string $email, mixed $password): void
    {
        $user = User::withTrashed()->where('email', $email)->first();
        $matches = Hash::check(is_string($password) ? $password : '', $user !== null ? (string) $user->password : DummyHash::VALUE);

        if ($user !== null && $matches && $user->status === UserStatus::Suspended && ! $user->trashed()) {
            throw ValidationException::withMessages([
                'data.email' => __('This account is suspended. Contact an administrator if you think this is a mistake.'),
            ]);
        }
    }

    public function getSubheading(): string|Htmlable|null
    {
        return app(AuthSettings::class)->registration_enabled ? parent::getSubheading() : null;
    }
}
