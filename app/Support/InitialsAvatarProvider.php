<?php

declare(strict_types=1);

namespace App\Support;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Draws initials locally. Filament's default sends every user's name to ui-avatars.com.
 */
final class InitialsAvatarProvider implements AvatarProvider
{
    public function get(Model $record): string
    {
        $name = $record instanceof \Illuminate\Contracts\Auth\Authenticatable ? Filament::getNameForDefaultAvatar($record) : '';
        $initials = Str::of($name)->trim()->explode(' ')->filter()->take(2)
            ->map(fn (string $part): string => Str::upper(Str::substr($part, 0, 1)))->implode('');
        $initials = htmlspecialchars($initials === '' ? '?' : $initials, ENT_XML1);

        $svg = <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64"><rect width="64" height="64" fill="#0f766e"/><text x="50%" y="50%" dy=".35em" text-anchor="middle" font-family="sans-serif" font-size="26" font-weight="600" fill="#ffffff">{$initials}</text></svg>
            SVG;

        return 'data:image/svg+xml;base64,'.base64_encode(trim($svg));
    }
}
