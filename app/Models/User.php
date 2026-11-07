<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SystemRole;
use App\Enums\UserStatus;
use App\Support\AccessCeiling;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

/**
 * Status is changed through SuspendUser and ActivateUser, never mass-assigned.
 * Two-factor secrets and recovery codes are stored encrypted and never serialised.
 *
 * @property string $email
 * @property string $name
 * @property UserStatus $status
 * @property ?string $avatar_path
 * @property ?\Illuminate\Support\Carbon $email_verified_at
 * @property ?\Illuminate\Support\Carbon $created_at
 * @property ?\Illuminate\Support\Carbon $updated_at
 * @property ?\Illuminate\Support\Carbon $deleted_at
 */
#[Fillable(['name', 'email', 'password', 'avatar_path'])]
#[Hidden(['password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes'])]
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasAvatar, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, InteractsWithAppAuthentication, InteractsWithAppAuthenticationRecovery, LogsActivity, Notifiable, SoftDeletes;

    /**
     * Roles and permissions always belong to the web guard, also while an API
     * request (the sanctum guard) is being handled.
     */
    protected string $guard_name = 'web';

    /** Where avatars live on the private "local" disk. Paths outside it are ignored. */
    public const AVATAR_DIRECTORY = 'avatars';

    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * Logs changes to these fields only. Passwords and two-factor secrets are
     * logged as events (see booted) without their values.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('users')
            ->logOnly(['name', 'email', 'status', 'email_verified_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $event): string => ucfirst($event).' user');
    }

    protected static function booted(): void
    {
        self::updated(function (self $user): void {
            $event = match (true) {
                $user->wasChanged('app_authentication_secret') => filled($user->app_authentication_secret) ? 'two-factor-enabled' : 'two-factor-disabled',
                $user->wasChanged('app_authentication_recovery_codes') => 'recovery-codes-regenerated',
                default => null,
            };

            if ($event !== null) {
                activity('auth')->performedOn($user)->event($event)->log(str_replace('-', ' ', ucfirst($event)));
            }

            if ($user->wasChanged('password')) {
                activity('auth')->performedOn($user)->event('password-changed')->log('Password changed');
            }
        });

        // Only real avatar paths are ever stored. A tampered upload can't point the
        // photo at another private file, which deleting it later would remove.
        self::saving(function (self $user): void {
            if ($user->isDirty('avatar_path') && $user->avatar_path !== null && ! self::isAvatarPath($user->avatar_path)) {
                $user->avatar_path = $user->getOriginal('avatar_path');
            }
        });

        // An old photo is deleted as soon as it's replaced or removed, and with the account.
        self::updated(function (self $user): void {
            $old = $user->getOriginal('avatar_path');

            if ($user->wasChanged('avatar_path') && self::isAvatarPath($old)) {
                Storage::disk('local')->delete((string) $old);
            }

            // A new password ends every API token, as it would end a stolen one.
            if ($user->wasChanged('password')) {
                $user->tokens()->delete();
            }
        });

        // Deleting (also soft) revokes API tokens, so restoring someone doesn't bring them back.
        self::deleted(function (self $user): void {
            $user->tokens()->delete();
        });

        self::forceDeleted(function (self $user): void {
            $user->tokens()->delete();

            if (self::isAvatarPath($user->avatar_path)) {
                Storage::disk('local')->delete((string) $user->avatar_path);
            }
        });
    }

    /**
     * True when this request came in with an API token, which carries its own,
     * smaller set of permissions.
     */
    public function usesLimitedToken(): bool
    {
        // Sanctum types this as a PersonalAccessToken, but tests and first-party
        // requests get a TransientToken, which has no limits of its own.
        return $this->currentAccessToken() instanceof PersonalAccessToken; // @phpstan-ignore instanceof.alwaysTrue
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isActive();
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active && ! $this->trashed();
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(SystemRole::SuperAdmin->value);
    }

    /**
     * Avatars are private files served through short-lived signed links.
     */
    public function getFilamentAvatarUrl(): ?string
    {
        $path = $this->avatar_path;

        return self::isAvatarPath($path) ? Storage::disk('local')->temporaryUrl((string) $path, now()->addMinutes(30)) : null;
    }

    /** Used by stechstudio/filament-impersonate. */
    public function canImpersonate(): bool
    {
        return $this->can('users.impersonate');
    }

    /**
     * Super admins can't be impersonated, nobody impersonates themselves, and the
     * impersonator's access must cover the target's.
     */
    public function canBeImpersonated(): bool
    {
        $actor = Auth::user();

        return ! $this->isSuperAdmin()
            && $this->isActive()
            && $actor instanceof self
            && ! $actor->is($this)
            && AccessCeiling::covers($actor, $this);
    }

    public static function isAvatarPath(mixed $path): bool
    {
        return is_string($path) && preg_match('#^'.self::AVATAR_DIRECTORY.'/[A-Za-z0-9_-]+\.(jpe?g|png|webp)$#', $path) === 1;
    }

    /**
     * @return HasMany<LoginEvent, $this>
     */
    public function loginEvents(): HasMany
    {
        return $this->hasMany(LoginEvent::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
        ];
    }
}
