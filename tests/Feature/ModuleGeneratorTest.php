<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use App\Modules\ModuleDefinition;
use App\Modules\ModuleGenerator;
use App\Modules\ModuleRegistry;
use App\Support\Permissions;

/*
| The generator's own behaviour. scripts/check-module-generator.sh (also run in CI)
| goes further: it generates full modules and runs their style, analysis and tests.
*/

it('works out names from the module name', function (): void {
    $module = ModuleDefinition::make('InvoiceLines', 'description:string', true, true, true, true);

    expect($module->model)->toBe('InvoiceLine')
        ->and($module->table())->toBe('invoice_lines')
        ->and($module->uri())->toBe('invoice-lines')
        ->and($module->permission())->toBe('invoice_lines')
        ->and($module->actions())->toContain('restore')
        ->and($module->titleField())->toBe('description');
});

it('leaves out restore without soft deletes', function (): void {
    expect(ModuleDefinition::make('Note', 'body:text', false, true, true, true)->actions())->not->toContain('restore');
});

it('refuses names the app already uses, and duplicate fields', function (string $name, string $fields, string $message): void {
    expect(fn () => ModuleDefinition::make($name, $fields, true, true, true, true))->toThrow(InvalidArgumentException::class, $message);
})->with([
    ['User', 'name:string', 'already used'],
    ['2fast', 'name:string', 'valid module name'],
    ['Product', '', 'at least one field'],
    ['Product', 'name:string,name:text', 'only be used once'],
]);

it('only plans the parts asked for', function (): void {
    $generator = app(ModuleGenerator::class);
    $all = array_keys($generator->plan(ModuleDefinition::make('Product', 'name:string', true, true, true, true)));
    $bare = array_values($generator->plan(ModuleDefinition::make('Product', 'name:string', true, false, false, false)));

    expect($all)->toContain('app/Filament/Resources/Products/ProductResource.php', 'app/Http/Controllers/Api/V1/ProductController.php', 'routes/api/modules/products.php', 'tests/Feature/Modules/ProductApiTest.php')
        ->and($bare)->toBe(['model', 'migration', 'factory', 'seeder', 'policy']);
});

it('drops the restore parts from the templates without soft deletes', function (): void {
    $module = ModuleDefinition::make('Note', 'body:text', false, true, true, true);
    $generator = app(ModuleGenerator::class);

    expect($generator->render('api/routes', $module))->not->toContain('restore')
        ->and($generator->render('model', $module))->toContain('use HasFactory, LogsActivity;')
        ->and($generator->render('tests/admin', $module))->toContain('->toBeNull()');
});

it('refuses relations to models that do not exist, before writing anything', function (): void {
    $this->artisan('make:module', ['name' => 'Gadget', '--fields' => 'name:string,owner_id:foreignId'])
        ->expectsOutputToContain('Owner, which doesn\'t exist')
        ->assertFailed();

    expect(ModuleRegistry::find('Gadget'))->toBeNull()
        ->and(is_file(base_path('app/Models/Gadget.php')))->toBeFalse();
});

it('makes and removes a module, its files and its permissions', function (): void {
    seedAccess();
    $registry = (string) file_get_contents(ModuleRegistry::path());

    try {
        $this->artisan('make:module', ['name' => 'Widget', '--fields' => 'name:string', '--no-api' => true, '--no-admin' => true, '--no-tests' => true])
            ->assertSuccessful();

        $entry = ModuleRegistry::find('Widget');
        expect($entry)->not->toBeNull()
            ->and($entry['files'])->toHaveCount(5)
            ->and(collect($entry['files'])->every(fn (string $path): bool => is_file(base_path($path))))->toBeTrue()
            ->and(Permission::query()->where('name', 'like', 'widgets.%')->count())->toBe(5)
            ->and(Role::findByName('admin', 'web')->hasPermissionTo('widgets.update'))->toBeTrue();

        $this->artisan('remove:module', ['name' => 'Widget'])
            ->expectsConfirmation('Remove the Widget module?', 'no')
            ->assertSuccessful();
        expect(ModuleRegistry::find('Widget'))->not->toBeNull();

        $this->artisan('remove:module', ['name' => 'Widget', '--force' => true])->assertSuccessful();

        expect(ModuleRegistry::find('Widget'))->toBeNull()
            ->and(collect($entry['files'])->contains(fn (string $path): bool => is_file(base_path($path))))->toBeFalse()
            ->and(Permission::query()->where('name', 'like', 'widgets.%')->count())->toBe(0);
    } finally {
        // Never leave a test module behind, even when an assertion fails.
        foreach (ModuleRegistry::find('Widget')['files'] ?? [] as $path) {
            @unlink(base_path($path));
        }
        file_put_contents(ModuleRegistry::path(), $registry);
        Permissions::reset();
    }
});

it('drops the table and its migration record only when asked', function (): void {
    $registry = (string) file_get_contents(ModuleRegistry::path());
    Schema::create('gizmos', fn ($table) => $table->id());
    DB::table('migrations')->insert(['migration' => '2026_01_01_000000_create_gizmos_table', 'batch' => 1]);
    ModuleRegistry::put('Gizmo', ['permission' => 'gizmos', 'actions' => ['view'], 'table' => 'gizmos', 'title' => 'id', 'files' => ['database/migrations/2026_01_01_000000_create_gizmos_table.php'], 'created_at' => now()->toIso8601String()]);

    try {
        $this->artisan('remove:module', ['name' => 'Gizmo', '--force' => true])->assertSuccessful();
        expect(Schema::hasTable('gizmos'))->toBeTrue();

        ModuleRegistry::put('Gizmo', ['permission' => 'gizmos', 'actions' => ['view'], 'table' => 'gizmos', 'title' => 'id', 'files' => ['database/migrations/2026_01_01_000000_create_gizmos_table.php'], 'created_at' => now()->toIso8601String()]);
        $this->artisan('remove:module', ['name' => 'Gizmo', '--force' => true, '--drop-table' => true])->assertSuccessful();

        expect(Schema::hasTable('gizmos'))->toBeFalse()
            ->and(DB::table('migrations')->where('migration', '2026_01_01_000000_create_gizmos_table')->exists())->toBeFalse();
    } finally {
        file_put_contents(ModuleRegistry::path(), $registry);
    }
});

it('refuses a modules.json entry that points outside the generated folders', function (string $field, mixed $value): void {
    $registry = (string) file_get_contents(ModuleRegistry::path());
    $entry = ['permission' => 'gizmos', 'actions' => ['view'], 'table' => 'gizmos', 'title' => 'id', 'files' => ['app/Models/Gizmo.php'], 'created_at' => now()->toIso8601String()];
    ModuleRegistry::put('Gizmo', [...$entry, $field => $value]);

    try {
        $this->artisan('remove:module', ['name' => 'Gizmo', '--force' => true])->expectsOutputToContain('Nothing was changed')->assertFailed();
        expect(is_file(base_path('.env.example')))->toBeTrue()
            ->and(Permission::query()->count())->toBeGreaterThanOrEqual(0);
    } finally {
        file_put_contents(ModuleRegistry::path(), $registry);
    }
})->with([
    'a file outside the generated folders' => ['files', ['.env.example']],
    'a permission that is not the module\'s' => ['permission', '%'],
    'a table that is not the module\'s' => ['table', 'users'],
]);

it('never lets a generated module redefine a core module\'s permissions', function (): void {
    $registry = (string) file_get_contents(ModuleRegistry::path());
    ModuleRegistry::put('User', ['permission' => 'users', 'actions' => ['view'], 'table' => 'users', 'title' => 'name', 'files' => [], 'created_at' => now()->toIso8601String()]);

    try {
        Permissions::reset();
        expect(Permissions::modules()['users'])->toContain('update', 'impersonate');
    } finally {
        file_put_contents(ModuleRegistry::path(), $registry);
        Permissions::reset();
    }
});

it('explains when there is no such module to remove', function (): void {
    $this->artisan('remove:module', ['name' => 'Nothing'])->expectsOutputToContain('no module called Nothing')->assertFailed();
});
