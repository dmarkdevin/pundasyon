<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Settings\AuthSettings;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Two locks against password guessing:
 * - per email and address: 5 failures (changeable) locks that email from that
 *   address, so an attacker can't lock the real owner out from elsewhere;
 * - per email from anywhere: 10 times as many locks the account everywhere,
 *   which caps slow guessing spread over many addresses.
 * Emails without an account are locked the same way, so the message can't
 * reveal which emails exist.
 */
final readonly class AccountLockout
{
    private const ACCOUNT_WIDE_FACTOR = 10;

    public function __construct(private AuthSettings $settings) {}

    public function isLocked(string $email, ?string $ip): bool
    {
        return RateLimiter::tooManyAttempts($this->pairKey($email, $ip), $this->settings->lockout_attempts)
            || RateLimiter::tooManyAttempts($this->accountKey($email), $this->settings->lockout_attempts * self::ACCOUNT_WIDE_FACTOR);
    }

    public function secondsLeft(string $email, ?string $ip): int
    {
        return max(RateLimiter::availableIn($this->pairKey($email, $ip)), RateLimiter::availableIn($this->accountKey($email)));
    }

    public function recordFailure(string $email, ?string $ip): void
    {
        $decay = $this->settings->lockout_minutes * 60;

        RateLimiter::hit($this->pairKey($email, $ip), $decay);
        RateLimiter::hit($this->accountKey($email), $decay);
    }

    /**
     * After a successful sign-in or password reset from this address.
     */
    public function clear(string $email, ?string $ip): void
    {
        RateLimiter::clear($this->pairKey($email, $ip));
        RateLimiter::clear($this->accountKey($email));
    }

    private function pairKey(string $email, ?string $ip): string
    {
        return 'lockout:pair:'.hash('sha256', Str::lower(trim($email)).'|'.$ip);
    }

    private function accountKey(string $email): string
    {
        return 'lockout:account:'.hash('sha256', Str::lower(trim($email)));
    }
}
