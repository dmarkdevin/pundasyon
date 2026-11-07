<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\LoginEventType;
use App\Models\LoginEvent;
use App\Models\User;
use App\Support\Auth\AccountLockout;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Writes the sign-in history and counts failures towards a lockout.
 * Only the email is taken from the attempt, never the password.
 */
final readonly class RecordLoginEvents
{
    public function __construct(private Request $request, private AccountLockout $lockout) {}

    public function handleLogin(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $this->lockout->clear($event->user->email, $this->request->ip());
        $this->record(LoginEventType::Succeeded, $event->user->email, $event->user);
    }

    public function handleFailed(Failed $event): void
    {
        $email = $event->credentials['email'] ?? null;

        if (! is_string($email) || $email === '') {
            return;
        }

        $this->lockout->recordFailure($email, $this->request->ip());
        $this->record(LoginEventType::Failed, $email, $event->user);
    }

    /**
     * A reset proves the person owns the account, so it lifts the lock: the
     * lock message tells people to reset their password.
     */
    public function handlePasswordReset(PasswordReset $event): void
    {
        if ($event->user instanceof User) {
            $this->lockout->clear($event->user->email, $this->request->ip());
            activity('auth')->performedOn($event->user)->event('password-reset')->withProperties($this->origin())->log('Reset password');
        }
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user instanceof User) {
            activity('auth')->causedBy($event->user)->performedOn($event->user)->event('signed-out')
                ->withProperties($this->origin())->log('Signed out');
        }
    }

    public function record(LoginEventType $type, string $email, ?Authenticatable $user = null): void
    {
        $entry = activity('auth')->event(match ($type) {
            LoginEventType::Succeeded => 'signed-in',
            LoginEventType::Failed => 'sign-in-failed',
            LoginEventType::LockedOut => 'locked-out',
        })->withProperties([...$this->origin(), 'email' => Str::lower(trim($email))]);

        if ($user instanceof User) {
            $entry->performedOn($user);

            if ($type === LoginEventType::Succeeded) {
                $entry->causedBy($user);
            }
        }

        $entry->log($type->label());

        LoginEvent::query()->create([
            'user_id' => $user instanceof User ? $user->getKey() : null,
            'email' => Str::limit(Str::lower(trim($email)), 250, ''),
            'type' => $type,
            'ip_address' => $this->request->ip(),
            'user_agent' => Str::limit((string) $this->request->userAgent(), 500, ''),
        ]);
    }

    /**
     * @return array{ip: ?string, user_agent: string}
     */
    private function origin(): array
    {
        return ['ip' => $this->request->ip(), 'user_agent' => Str::limit((string) $this->request->userAgent(), 300, '')];
    }
}
