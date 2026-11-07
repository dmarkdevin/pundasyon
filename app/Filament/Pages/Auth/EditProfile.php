<?php

declare(strict_types=1);

namespace App\Filament\Pages\Auth;

use App\Models\User;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Schema;

/**
 * Filament's profile page, plus a profile photo. Two-factor set-up is added by Filament.
 */
final class EditProfile extends BaseEditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            FileUpload::make('avatar_path')
                ->label('Profile photo')
                ->avatar()
                ->image()
                ->imageEditor()
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->maxSize(2048)
                // Private disk with random file names; shown through signed links.
                ->disk('local')
                ->directory(User::AVATAR_DIRECTORY)
                ->visibility('private')
                // Refuse any path the browser sends that this upload didn't create.
                ->preventFilePathTampering(),
            $this->getNameFormComponent(),
            $this->getEmailFormComponent(),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
            $this->getCurrentPasswordFormComponent(),
        ]);
    }
}
