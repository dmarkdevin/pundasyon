<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Permissions\SyncPermissions;
use App\Modules\ModuleDefinition;
use App\Modules\ModuleGenerator;
use App\Support\Permissions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

#[Signature('make:module
    {name : Singular, in StudlyCase, such as Product}
    {--fields= : Comma-separated name:type pairs, such as "name:string,price:decimal,category_id:foreignId". Add :nullable or :unique after a type}
    {--no-api : Skip the API endpoints}
    {--no-admin : Skip the admin screens}
    {--no-tests : Skip the tests}
    {--soft-deletes=true : Keep deleted records so they can be restored (true or false)}')]
#[Description('Create a module: model, migration, factory, seeder, policy, permissions, admin screens, API and tests')]
final class MakeModuleCommand extends Command
{
    public function handle(ModuleGenerator $generator, SyncPermissions $sync): int
    {
        try {
            $module = ModuleDefinition::make(
                (string) $this->argument('name'),
                (string) $this->option('fields'),
                filter_var($this->option('soft-deletes'), FILTER_VALIDATE_BOOL),
                ! $this->option('no-api'),
                ! $this->option('no-admin'),
                ! $this->option('no-tests'),
            );
            $files = $generator->generate($module);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Created the {$module->model} module.");
        $this->components->bulletList($files);

        // Permissions are read from modules.json, which just changed.
        Permissions::reset();

        try {
            $created = $sync();
            $this->components->info('Permissions created and given to admins: '.implode(', ', $created));
        } catch (Throwable) {
            $this->components->warn('Run `php artisan permissions:sync` once the database is up, to create the permissions.');
        }

        $this->components->twoColumnDetail('Next', 'php artisan migrate');

        if ($module->tests) {
            $this->components->twoColumnDetail('Then', "php artisan test --filter={$module->model}");
        }

        return self::SUCCESS;
    }
}
