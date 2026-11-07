<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\LoginEventType;
use App\Listeners\RecordLoginEvents;
use App\Models\User;
use App\Support\Auth\AccountLockout;
use App\Support\Auth\DummyHash;
use App\Support\Permissions;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;

/**
 * Signs in through the API: the same lockout, history and two-factor rules as
 * the web sign-in, then a token limited to the abilities asked for.
 */
final readonly class IssueApiToken
{
    /** API sign-in tokens expire unless a shorter time is asked for. */
    public const DEFAULT_EXPIRY_DAYS = 90;

    public function __construct(private AccountLockout $lockout, private RecordLoginEvents $events, private Request $request) {}

    /**
     * @param  list<string>|null  $abilities  null means every permission the person has
     * @param  int|null  $expiresInDays  null means DEFAULT_EXPIRY_DAYS
     */
    public function __invoke(string $email, string $password, string $deviceName, ?string $code, ?array $abilities, ?int $expiresInDays): NewAccessToken
    {
        if ($this->lockout->isLocked($email, $this->request->ip())) {
            $this->events->record(LoginEventType::LockedOut, $email);

            throw ValidationException::withMessages(['email' => __('Too many failed sign-ins for this account. Try again later, or reset your password.')]);
        }

        $user = User::query()->where('email', $email)->first();

        if (! Hash::check($password, $user !== null ? (string) $user->password : DummyHash::VALUE) || $user === null) {
            $this->lockout->recordFailure($email, $this->request->ip());
            $this->events->record(LoginEventType::Failed, $email, $user);

            throw ValidationException::withMessages(['email' => __('These credentials do not match our records.')]);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages(['email' => __('This account is suspended.')]);
        }

        if (! $user->hasVerifiedEmail()) {
            throw ValidationException::withMessages(['email' => __('Confirm your email address before using the API.')]);
        }

        $this->checkTwoFactor($user, $email, $code);

        $this->lockout->clear($email, $this->request->ip());
        $this->events->record(LoginEventType::Succeeded, $email, $user);

        $mine = $user->isSuperAdmin()
            ? Permissions::all()
            : array_values(array_map(strval(...), $user->getAllPermissions()->pluck('name')->all()));
        $granted = $abilities === null ? $mine : array_values(array_intersect($abilities, $mine));

        $token = $user->createToken($deviceName, $granted, Carbon::now()->addDays($expiresInDays ?? self::DEFAULT_EXPIRY_DAYS));

        activity('auth')->causedBy($user)->performedOn($user)->event('token-created')
            ->withProperties(['token' => $deviceName, 'abilities' => $granted, 'expires_at' => $token->accessToken->expires_at?->toIso8601String(), 'via' => 'api'])
            ->log('Created an API token');

        return $token;
    }

    /**
     * Someone with two-factor turned on must send a current code. Recovery codes
     * only work on the web sign-in page.
     */
    private function checkTwoFactor(User $user, string $email, ?string $code): void
    {
        if (blank($user->getAppAuthenticationSecret())) {
            return;
        }

        if (blank($code)) {
            throw ValidationException::withMessages(['code' => __('This account uses two-factor sign-in. Send the 6-digit code from the authenticator app as "code".')]);
        }

        // A code works once, so one seen or phished can't mint more tokens.
        if (! AppAuthentication::make()->verifyCode((string) $code, $user->getAppAuthenticationSecret(), shouldPreventCodeReuse: true)) {
            $this->lockout->recordFailure($email, $this->request->ip());
            $this->events->record(LoginEventType::Failed, $email, $user);

            throw ValidationException::withMessages(['code' => __('That code isn\'t right. Codes change every 30 seconds.')]);
        }
    }
}
