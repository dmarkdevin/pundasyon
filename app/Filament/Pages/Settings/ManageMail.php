<?php

declare(strict_types=1);

namespace App\Filament\Pages\Settings;

use App\Models\User;
use App\Settings\MailSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class ManageMail extends SettingsPage
{
    protected static string $settings = MailSettings::class;

    protected static ?string $title = 'Email';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'settings/email';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Sender')
                ->description('Leave empty to use MAIL_FROM_ADDRESS from .env and the app name.')
                ->columns(2)
                ->schema([
                    TextInput::make('from_address')->label('From address')->email()->maxLength(255),
                    TextInput::make('from_name')->label('From name')->maxLength(100),
                ]),
            Section::make('Mail server (SMTP)')
                ->description('Leave the host empty to use the mail settings in .env.')
                ->columns(2)
                ->schema([
                    TextInput::make('smtp_host')->label('Host')->maxLength(255),
                    TextInput::make('smtp_port')->label('Port')->integer()->minValue(1)->maxValue(65535)->placeholder('587'),
                    TextInput::make('smtp_username')->label('Username')->maxLength(255),
                    TextInput::make('smtp_password')
                        ->label('Password')
                        ->password()
                        ->revealable()
                        // Never sent back to the browser; empty means "keep the saved one".
                        ->formatStateUsing(fn (): ?string => null)
                        ->placeholder(fn (): string => filled(app(MailSettings::class)->smtp_password) ? 'Saved. Type a new one to change it.' : '')
                        ->maxLength(255),
                    Select::make('smtp_encryption')
                        ->label('Encryption')
                        ->options(['tls' => 'TLS (STARTTLS, usually port 587)', 'ssl' => 'SSL (usually port 465)'])
                        ->placeholder('None'),
                ]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (blank($data['smtp_password'] ?? null)) {
            $data['smtp_password'] = app(MailSettings::class)->smtp_password;
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendTest')
                ->label('Send a test email')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->modalDescription(fn (): string => 'Sends a short email to '.(Auth::user() instanceof User ? Auth::user()->email : 'you').' using the saved settings.')
                ->action(function (): void {
                    $email = Auth::user() instanceof User ? Auth::user()->email : '';

                    try {
                        Mail::raw('This is a test email from '.config('app.name').'. Your mail settings work.', function ($message) use ($email): void {
                            $message->to($email)->subject('Test email from '.config('app.name'));
                        });
                    } catch (Throwable $exception) {
                        Notification::make()->title('The email couldn\'t be sent.')->body($exception->getMessage())->danger()->persistent()->send();

                        return;
                    }

                    Notification::make()->title("Sent to {$email}.")->success()->send();
                }),
        ];
    }
}
