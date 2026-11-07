<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\ModuleRemover;
use App\Support\Permissions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('remove:module
    {name : The module to remove, such as Product}
    {--drop-table : Also delete the database table and all its records}
    {--force : Don\'t ask for confirmation}')]
#[Description('Remove a module made with make:module: its files, permissions and, if asked, its table')]
final class RemoveModuleCommand extends Command
{
    public function handle(ModuleRemover $remover): int
    {
        $model = (string) $this->argument('name');

        try {
            $entry = $remover->entry($model);
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->warn("This deletes {$model}'s ".count($entry['files'])." files and the {$entry['permission']}.* permissions. It can't be undone.");
        $this->components->bulletList($entry['files']);

        if (! $this->option('force') && ! $this->confirm("Remove the {$model} module?")) {
            $this->components->info('Nothing was changed.');

            return self::SUCCESS;
        }

        $dropTable = (bool) $this->option('drop-table');

        if (! $dropTable && ! $this->option('force') && $remover->tableExists($model)) {
            $dropTable = $this->confirm("Also delete the {$entry['table']} table and all its records?", false);
        }

        $remover->remove($model, $dropTable);
        Permissions::reset();

        $this->components->info("Removed the {$model} module.".($dropTable ? " The {$entry['table']} table was deleted." : ''));

        if (! $dropTable) {
            $this->components->twoColumnDetail('The table stays', "Drop {$entry['table']} yourself when you no longer need its data.");
        }

        return self::SUCCESS;
    }
}
