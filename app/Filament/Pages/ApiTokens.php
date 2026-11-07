<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\User;
use App\Support\Permissions;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Where people make and revoke their own API tokens. A token can only have
 * permissions its owner has, and is shown once, when it's made.
 */
final class ApiTokens extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'API tokens';

    protected static ?string $slug = 'api-tokens';

    protected string $view = 'filament.pages.api-tokens';

    /** Shown once after creating a token, then forgotten. */
    public ?string $newToken = null;

    /**
     * @return Collection<int, PersonalAccessToken>
     */
    public function getTokens(): Collection
    {
        return $this->user()->tokens()->latest()->get();
    }

    public function abilityLabel(string $ability): string
    {
        return Permissions::label($ability);
    }

    public function createTokenAction(): Action
    {
        return Action::make('createToken')
            ->label('New token')
            ->modalDescription('The token can only do what you allow here, and never more than your own permissions.')
            ->schema([
                TextInput::make('name')
                    ->label('What is it for?')
                    ->placeholder('For example, accounting sync')
                    ->required()
                    ->maxLength(100),
                CheckboxList::make('abilities')
                    ->label('Permissions')
                    ->options(fn (): array => collect($this->myPermissions())->mapWithKeys(fn (string $name): array => [$name => Permissions::label($name)])->all())
                    ->in(fn (): array => $this->myPermissions())
                    ->columns(2)
                    ->bulkToggleable()
                    ->helperText('Leave all unticked for a token that can only read your own account (GET /api/v1/auth/me).'),
                Select::make('expires_in_days')
                    ->label('Expires')
                    ->options(['30' => 'In 30 days', '90' => 'In 90 days', '365' => 'In a year', 'never' => 'Never'])
                    ->default('90')
                    ->required(),
            ])
            ->action(function (array $data): void {
                $days = $data['expires_in_days'] === 'never' ? null : (int) $data['expires_in_days'];
                $abilities = array_values(array_intersect($data['abilities'] ?? [], $this->myPermissions()));

                $token = $this->user()->createToken($data['name'], $abilities, $days === null ? null : Carbon::now()->addDays($days));
                $this->newToken = $token->plainTextToken;

                activity('auth')->performedOn($this->user())->event('token-created')
                    ->withProperties(['token' => $data['name'], 'abilities' => $abilities, 'expires_at' => $token->accessToken->expires_at?->toIso8601String()])
                    ->log('Created an API token');
            });
    }

    public function revokeTokenAction(): Action
    {
        return Action::make('revokeToken')
            ->label('Revoke')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('Anything using this token stops working at once.')
            ->action(function (array $arguments): void {
                $token = $this->user()->tokens()->whereKey($arguments['token'] ?? 0)->first();

                if ($token === null) {
                    return;
                }

                activity('auth')->performedOn($this->user())->event('token-revoked')->withProperties(['token' => $token->name])->log('Revoked an API token');
                $token->delete();
                Notification::make()->title('Token revoked.')->success()->send();
            });
    }

    public function forgetNewToken(): void
    {
        $this->newToken = null;
    }

    /**
     * @return list<string>
     */
    private function myPermissions(): array
    {
        $user = $this->user();

        return $user->isSuperAdmin()
            ? Permissions::all()
            : array_values(array_map(strval(...), $user->getAllPermissions()->pluck('name')->sort()->all()));
    }

    private function user(): User
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
