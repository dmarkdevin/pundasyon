<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Settings\ApplySettings;
use App\Enums\SystemRole;
use App\Models\User;
use App\Policies\ActivityPolicy;
use App\Settings\AuthSettings;
use App\Support\Permissions;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Activitylog\Models\Activity;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Shown in this zone, stored in UTC.
        FilamentTimezone::set((string) config('app.display_timezone'));

        // Database settings (app name, mail) override .env. Also runs in queue workers.
        app(ApplySettings::class)();

        // The activity log model comes from a package, so its policy is registered here.
        Gate::policy(Activity::class, ActivityPolicy::class);

        // The single permission check for the whole app (spatie's own is turned off in
        // config/permission.php). In order:
        // 1. An API token only carries the permissions picked when it was made.
        // 2. Super admins can do everything, except delete their own account. With a
        //    limited token, policies still run, so the token's limits hold there too.
        // 3. Everyone else has a permission if a role or direct grant gives it.
        Gate::before(function (mixed $user, string $ability, array $arguments): ?bool {
            if (! $user instanceof User) {
                return null;
            }

            $isPermission = Permissions::exists($ability);

            if ($isPermission && $user->usesLimitedToken() && ! $user->tokenCan($ability)) {
                return false;
            }

            if ($user->isSuperAdmin()) {
                $target = $arguments[0] ?? null;
                $deletingSelf = in_array($ability, ['delete', 'forceDelete'], true) && $target instanceof User && $target->is($user);

                if (! $deletingSelf && ($isPermission || ! $user->usesLimitedToken())) {
                    return true;
                }

                return null;
            }

            return $isPermission && $user->checkPermissionTo($ability) ? true : null;
        });

        // In production the API docs are for super admins and admins only.
        Gate::define('viewApiDocs', fn (?User $user): bool => $user !== null && $user->hasAnyRole([SystemRole::SuperAdmin->value, SystemRole::Admin->value]));

        // Per token once signed in, per address before that.
        RateLimiter::for('api', function (Request $request): Limit {
            $token = $request->user()?->currentAccessToken();
            $key = $token instanceof PersonalAccessToken ? 'token:'.$token->getKey() : 'ip:'.$request->ip();

            return Limit::perMinute((int) config('security.api_rate_limit'))->by($key);
        });

        // Per email and address against guessing one account, and per address against
        // trying one password on many accounts.
        RateLimiter::for('api-login', fn (Request $request): array => [
            Limit::perMinute((int) config('security.api_login_rate_limit'))->by(Str::lower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute((int) config('security.api_login_rate_limit_per_address'))->by('address:'.$request->ip()),
        ]);

        // Read from config, not env(), so it still works when the config is cached.
        $proxies = config('security.trusted_proxies');
        TrustProxies::at(match (true) {
            $proxies === '*' => '*',
            is_string($proxies) && $proxies !== '' => array_map(trim(...), explode(',', $proxies)),
            default => [],
        });

        // The session cookie only travels over HTTPS in production, unless .env says otherwise.
        if ($this->app->isProduction() && config('session.secure') === null) {
            config(['session.secure' => true]);
        }

        // Links and redirects use https:// in production, even behind a proxy.
        URL::forceHttps($this->app->isProduction() && (bool) config('security.force_https'));

        // Used by sign-up, password reset and the profile page. Read when a password is
        // checked, not at boot, so the settings take effect at once and boot needs no database.
        Password::defaults(function (): Password {
            $settings = app(AuthSettings::class);
            $rule = Password::min($settings->password_min_length)->max(255);

            if ($settings->password_requires_mixed_case) {
                $rule->mixedCase();
            }

            if ($settings->password_requires_numbers) {
                $rule->numbers();
            }

            if ($settings->password_requires_symbols) {
                $rule->symbols();
            }

            if ($settings->password_must_not_be_breached) {
                $rule->uncompromised();
            }

            return $rule;
        });
    }
}
