<?php

declare(strict_types=1);

namespace App\Modules;

use App\Models\Permission;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

/**
 * Takes out what make:module put in: its files, its permissions and its entry in
 * modules.json. The database table is only dropped when asked, since it holds data.
 */
final readonly class ModuleRemover
{
    public function __construct(private Filesystem $files) {}

    /**
     * @return array{permission: string, actions: list<string>, table: string, title: string, files: list<string>, created_at: string}
     */
    public function entry(string $model): array
    {
        $entry = ModuleRegistry::find($model) ?? throw new RuntimeException("There's no module called {$model}. Modules made with make:module are listed in modules.json.");

        $this->check($model, $entry);

        return $entry;
    }

    /**
     * @return list<string> the files deleted
     */
    public function remove(string $model, bool $dropTable): array
    {
        $entry = $this->entry($model);
        $deleted = [];

        foreach ($entry['files'] as $path) {
            // Paths were checked against the generator's folders in check().
            if (! $this->files->exists(base_path($path))) {
                continue;
            }

            $this->files->delete(base_path($path));
            $deleted[] = $path;
            $this->removeEmptyFolders(dirname(base_path($path)));
        }

        // By exact name: a LIKE pattern from the file could match every permission.
        Permission::query()->whereIn('name', array_map(fn (string $action): string => "{$entry['permission']}.{$action}", $entry['actions']))->get()->each->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if ($dropTable) {
            Schema::dropIfExists($entry['table']);

            // The migration's file is gone, so its "already ran" record goes too.
            foreach ($entry['files'] as $path) {
                if (str_starts_with($path, 'database/migrations/')) {
                    DB::table((string) config('database.migrations.table', 'migrations'))->where('migration', basename($path, '.php'))->delete();
                }
            }
        }

        ModuleRegistry::forget($model);

        return $deleted;
    }

    /**
     * modules.json is a file anyone with commit access can change, so its entry
     * must match what make:module would have written for this model: names derived
     * from the model, and only files in the folders the generator writes to.
     *
     * @param  array{permission: string, actions: list<string>, table: string, title: string, files: list<string>, created_at: string}  $entry
     */
    private function check(string $model, array $entry): void
    {
        if (preg_match('/^[A-Z][A-Za-z0-9]*$/', $model) !== 1) {
            throw new RuntimeException("{$model} isn't a module name make:module would use.");
        }

        $models = Str::pluralStudly($model);
        $table = Str::snake($models);
        $uri = Str::kebab($models);

        if ($entry['permission'] !== $table || $entry['table'] !== $table || array_diff($entry['actions'], ['view', 'create', 'update', 'delete', 'restore']) !== []) {
            throw new RuntimeException("The modules.json entry for {$model} doesn't match what make:module writes. Nothing was changed.");
        }

        $allowed = [
            "#^app/Models/{$model}\.php$#",
            "#^database/migrations/\d{4}_\d{2}_\d{2}_\d{6}_create_{$table}_table\.php$#",
            "#^database/factories/{$model}Factory\.php$#",
            "#^database/seeders/{$model}Seeder\.php$#",
            "#^app/Policies/{$model}Policy\.php$#",
            "#^app/Filament/Resources/{$models}/[A-Za-z/]+\.php$#",
            "#^app/Http/Controllers/Api/V1/{$model}Controller\.php$#",
            "#^app/Http/Requests/Api/V1/(Store|Update){$model}Request\.php$#",
            "#^app/Http/Resources/V1/{$model}Resource\.php$#",
            "#^routes/api/modules/{$uri}\.php$#",
            "#^tests/Feature/Modules/{$model}(Admin|Api)Test\.php$#",
        ];

        foreach ($entry['files'] as $path) {
            if (! collect($allowed)->contains(fn (string $pattern): bool => preg_match($pattern, $path) === 1)) {
                throw new RuntimeException("modules.json lists {$path} for {$model}, which make:module never writes. Nothing was changed.");
            }
        }
    }

    public function tableExists(string $model): bool
    {
        return Schema::hasTable($this->entry($model)['table']);
    }

    private function removeEmptyFolders(string $directory): void
    {
        $stopAt = [base_path('app'), base_path('app/Filament/Resources'), base_path('tests'), base_path('routes/api/modules'), base_path('database')];

        while (! in_array($directory, $stopAt, true) && str_starts_with($directory, base_path()) && $this->files->isDirectory($directory) && $this->files->isEmptyDirectory($directory)) {
            $this->files->deleteDirectory($directory);
            $directory = dirname($directory);
        }
    }
}
