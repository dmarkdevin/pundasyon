<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Permissions\SyncPermissions;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Creates the permissions and built-in roles. Accounts are made with
     * `php artisan app:install` (Phase 7) or by signing up.
     */
    public function run(SyncPermissions $sync): void
    {
        $sync();
    }
}
