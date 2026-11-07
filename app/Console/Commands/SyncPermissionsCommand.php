<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Permissions\SyncPermissions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('permissions:sync')]
#[Description('Create any new permissions and the built-in roles. Run after deploying or adding a module.')]
final class SyncPermissionsCommand extends Command
{
    public function handle(SyncPermissions $sync): int
    {
        $created = $sync();

        $this->components->info($created === [] ? 'Permissions are up to date.' : 'Created: '.implode(', ', $created));

        return self::SUCCESS;
    }
}
