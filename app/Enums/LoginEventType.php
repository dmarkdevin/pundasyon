<?php

declare(strict_types=1);

namespace App\Enums;

enum LoginEventType: string
{
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case LockedOut = 'locked_out';

    public function label(): string
    {
        return match ($this) {
            self::Succeeded => 'Signed in',
            self::Failed => 'Wrong email or password',
            self::LockedOut => 'Blocked: too many failed attempts',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Succeeded => 'success',
            self::Failed => 'warning',
            self::LockedOut => 'danger',
        };
    }
}
