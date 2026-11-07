<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Pages\ApiTokens;
use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\Register;
use App\Filament\Pages\Auth\RequestPasswordReset;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Security;
use App\Http\Middleware\EnsureUserIsActive;
use App\Settings\GeneralSettings;
use App\Support\InitialsAvatarProvider;
use Filament\Actions\Action;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * The one Filament panel. Everyone signs in here; what they see depends on their permissions.
 */
class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('app')
            ->path('app')
            ->viteTheme('resources/css/filament/app/theme.css')
            // Read from the General settings on every page, so changes show at once.
            ->brandName(fn (): string => (string) config('app.name'))
            ->brandLogo(function (): ?string {
                $logo = rescue(fn () => app(GeneralSettings::class)->logo_path, null, false);

                return filled($logo) ? Storage::disk('public')->url($logo) : null;
            })
            ->brandLogoHeight('2rem')
            ->login(Login::class)
            ->registration(Register::class)
            ->passwordReset(RequestPasswordReset::class)
            ->emailVerification()
            ->profile(EditProfile::class, isSimple: false)
            ->emailChangeVerification()
            ->multiFactorAuthentication([
                // Authenticator apps show the panel's brand name, which comes from the General settings.
                AppAuthentication::make()->recoverable(),
            ])
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            ->userMenuItems([
                Action::make('security')
                    ->label('Sign-ins and devices')
                    ->icon('heroicon-o-shield-check')
                    ->url(fn (): string => Security::getUrl()),
                Action::make('apiTokens')
                    ->label('API tokens')
                    ->icon('heroicon-o-key')
                    ->url(fn (): string => ApiTokens::getUrl()),
            ])
            ->colors([
                'primary' => Color::Teal,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->navigationGroups(['Access', 'Settings'])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                // Filament's Authenticate, plus signing out suspended people.
                EnsureUserIsActive::class,
            ]);
    }
}
