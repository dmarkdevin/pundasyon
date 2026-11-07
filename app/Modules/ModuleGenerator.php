<?php

declare(strict_types=1);

namespace App\Modules;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Writes a module's files from the templates in stubs/module and records them
 * in modules.json. Change the templates to change what every new module looks like.
 */
final readonly class ModuleGenerator
{
    public function __construct(private Filesystem $files) {}

    /**
     * Checks the module can be made, before anything is written.
     */
    public function check(ModuleDefinition $module): void
    {
        if (ModuleRegistry::find($module->model) !== null || class_exists("App\\Models\\{$module->model}")) {
            throw new RuntimeException("{$module->model} already exists. Remove it first with `php artisan remove:module {$module->model}`.");
        }

        foreach ($module->foreignKeys() as $field) {
            if (! class_exists($field->relatedModelClass())) {
                throw new RuntimeException("{$field->name} points to {$field->relatedModel()}, which doesn't exist. Make that module first.");
            }
        }

        foreach ($this->plan($module) as $path => $stub) {
            if ($this->files->exists(base_path($path))) {
                throw new RuntimeException("{$path} already exists. Nothing was changed.");
            }
        }
    }

    /**
     * @return list<string> the files written, relative to the project
     */
    public function generate(ModuleDefinition $module): array
    {
        $this->check($module);
        $written = [];

        foreach ($this->plan($module) as $path => $stub) {
            $this->files->ensureDirectoryExists(dirname(base_path($path)));
            $this->files->put(base_path($path), $this->render($stub, $module));
            $written[] = $path;
        }

        ModuleRegistry::put($module->model, [
            'permission' => $module->permission(),
            'actions' => $module->actions(),
            'table' => $module->table(),
            'title' => $module->titleField(),
            'files' => $written,
            'created_at' => now()->toIso8601String(),
        ]);

        $this->format($written);

        return $written;
    }

    /**
     * Which template goes where, depending on the options.
     *
     * @return array<string, string> path => stub
     */
    public function plan(ModuleDefinition $module): array
    {
        $m = $module->model;
        $ms = $module->models();
        $plan = [
            "app/Models/{$m}.php" => 'model',
            'database/migrations/'.now()->format('Y_m_d_His')."_create_{$module->table()}_table.php" => 'migration',
            "database/factories/{$m}Factory.php" => 'factory',
            "database/seeders/{$m}Seeder.php" => 'seeder',
            "app/Policies/{$m}Policy.php" => 'policy',
        ];

        if ($module->admin) {
            $plan += [
                "app/Filament/Resources/{$ms}/{$m}Resource.php" => 'filament/resource',
                "app/Filament/Resources/{$ms}/Schemas/{$m}Form.php" => 'filament/form',
                "app/Filament/Resources/{$ms}/Schemas/{$m}Infolist.php" => 'filament/infolist',
                "app/Filament/Resources/{$ms}/Tables/{$ms}Table.php" => 'filament/table',
                "app/Filament/Resources/{$ms}/Pages/List{$ms}.php" => 'filament/page-list',
                "app/Filament/Resources/{$ms}/Pages/Create{$m}.php" => 'filament/page-create',
                "app/Filament/Resources/{$ms}/Pages/Edit{$m}.php" => 'filament/page-edit',
                "app/Filament/Resources/{$ms}/Pages/View{$m}.php" => 'filament/page-view',
            ];
        }

        if ($module->api) {
            $plan += [
                "app/Http/Controllers/Api/V1/{$m}Controller.php" => 'api/controller',
                "app/Http/Requests/Api/V1/Store{$m}Request.php" => 'api/store-request',
                "app/Http/Requests/Api/V1/Update{$m}Request.php" => 'api/update-request',
                "app/Http/Resources/V1/{$m}Resource.php" => 'api/resource',
                "routes/api/modules/{$module->uri()}.php" => 'api/routes',
            ];
        }

        if ($module->tests && $module->admin) {
            $plan["tests/Feature/Modules/{$m}AdminTest.php"] = 'tests/admin';
        }

        if ($module->tests && $module->api) {
            $plan["tests/Feature/Modules/{$m}ApiTest.php"] = 'tests/api';
        }

        return $plan;
    }

    public function render(string $stub, ModuleDefinition $module): string
    {
        $template = $this->files->get(base_path("stubs/module/{$stub}.stub"));
        $template = $this->sections($template, [
            'softDeletes' => $module->softDeletes,
            'hasRequired' => $module->firstRequiredField() !== null,
            'hasUnique' => $module->firstUniqueField() !== null,
        ]);

        return strtr($template, $this->replacements($module));
    }

    /**
     * Keeps {{#name}}...{{/name}} when the condition holds, and {{^name}}...{{/name}} when it doesn't.
     *
     * @param  array<string, bool>  $conditions
     */
    private function sections(string $template, array $conditions): string
    {
        foreach ($conditions as $name => $on) {
            $template = (string) preg_replace_callback(
                '/\{\{([#^])'.$name.'\}\}\n?(.*?)\{\{\/'.$name.'\}\}\n?/s',
                fn (array $match): string => ($match[1] === '#') === $on ? $match[2] : '',
                $template,
            );
        }

        return $template;
    }

    /**
     * @return array<string, string>
     */
    private function replacements(ModuleDefinition $module): array
    {
        $fields = $module->fields;
        $line = fn (int $indent, array $lines): string => implode("\n", array_map(fn (string $text): string => str_repeat(' ', $indent).$text, $lines));
        $relations = $module->foreignKeys();
        $with = $relations === [] ? '' : "\n            ->with(['".implode("', '", array_map(fn (Field $field): string => $field->relationName(), $relations))."'])";
        $load = $relations === [] ? '' : "->load(['".implode("', '", array_map(fn (Field $field): string => $field->relationName(), $relations))."'])";
        $required = $module->firstRequiredField();
        $sorts = array_map(fn (Field $field): string => "'{$field->name}'", array_values(array_filter($fields, fn (Field $field): bool => $field->type->isSortable())));
        $filters = array_values(array_filter(array_map(fn (Field $field): ?string => $field->type->apiFilter($field), $fields)));
        $filterNames = array_values(array_filter(array_map(fn (Field $field): ?string => $field->type->apiFilter($field) !== null ? "`filter[{$field->name}]`" : null, $fields)));

        $replacements = [
            '{{ Model }}' => $module->model,
            '{{ Models }}' => $module->models(),
            '{{ models }}' => lcfirst($module->models()),
            '{{ modelVariable }}' => $module->variable(),
            '{{ routeParameter }}' => $module->routeParameter(),
            '{{ table }}' => $module->table(),
            '{{ uri }}' => $module->uri(),
            '{{ permission }}' => $module->permission(),
            '{{ label }}' => $module->label(),
            '{{ pluralLabel }}' => $module->pluralLabel(),
            '{{ title }}' => $module->titleField(),
            '{{ eagerLoad }}' => $with,
            '{{ loadRelations }}' => $load,
            '{{ requiredField }}' => $required->name ?? '',
            '{{ uniqueField }}' => $module->firstUniqueField()->name ?? '',
            '{{ filterList }}' => $filterNames === [] ? 'none' : implode(', ', $filterNames),
            '{{ sortList }}' => implode(', ', array_map(fn (string $sort): string => '`'.trim($sort, "'").'`', [...$sorts, "'created_at'"])),
            '{{ properties }}' => $line(0, array_map(fn (Field $field): string => " * @property {$field->type->phpDocType()}".($field->nullable ? '|null' : '')." \${$field->name}", $fields)),
            '{{ fillable }}' => $line(8, array_map(fn (Field $field): string => "'{$field->name}',", $fields)),
            '{{ casts }}' => $line(12, array_values(array_filter(array_map(fn (Field $field): ?string => $field->type->cast() !== null ? "'{$field->name}' => '{$field->type->cast()}'," : null, $fields)))),
            '{{ relations }}' => implode('', array_map(fn (Field $field): string => <<<PHP

                    /**
                     * @return BelongsTo<\\{$field->relatedModelClass()}, \$this>
                     */
                    public function {$field->relationName()}(): BelongsTo
                    {
                        return \$this->belongsTo(\\{$field->relatedModelClass()}::class);
                    }

                PHP, $relations)),
            '{{ migrationColumns }}' => $line(12, array_map(fn (Field $field): string => $field->type->migrationColumn($field), $fields)),
            '{{ factoryDefinition }}' => $line(12, array_map(fn (Field $field): string => "'{$field->name}' => ".($field->nullable && $field->type !== FieldType::Boolean ? "fake()->boolean(80) ? {$field->type->factoryValue($field)} : null" : $field->type->factoryValue($field)).',', $fields)),
            '{{ formComponents }}' => $line(20, array_map(fn (Field $field): string => $field->type->formComponent($field).',', $fields)),
            '{{ tableColumns }}' => $line(16, array_map(fn (Field $field): string => $field->type->tableColumn($field, $field->name === $module->titleField()).',', $fields)),
            '{{ tableFilters }}' => $line(16, array_values(array_filter(array_map(fn (Field $field): ?string => ($filter = $field->type->tableFilter($field)) !== null ? $filter.',' : null, $fields)))),
            '{{ infolistEntries }}' => $line(20, array_map(fn (Field $field): string => $field->type->infolistEntry($field).',', $fields)),
            '{{ storeRules }}' => $line(12, array_map(fn (Field $field): string => "'{$field->name}' => [".implode(', ', [self::presence($field), ...$field->type->rules($field, $module->table())]).'],', $fields)),
            '{{ updateRules }}' => $line(12, array_map(fn (Field $field): string => "'{$field->name}' => [".implode(', ', array_unique(["'sometimes'", self::presence($field), ...$field->type->rules($field, $module->table())])).'],', $fields)),
            '{{ resourceFields }}' => $line(12, array_map(fn (Field $field): string => "'{$field->name}' => {$field->type->resourceValue($field)},", $fields)),
            '{{ apiFilters }}' => $line(16, array_map(fn (string $filter): string => $filter.',', $filters === [] ? ["AllowedFilter::exact('id')"] : $filters)),
            '{{ apiSorts }}' => implode(', ', [...$sorts, "'created_at'"]),
            '{{ testData }}' => $line(8, array_map(fn (Field $field): string => "'{$field->name}' => ".match ($field->type) {
                FieldType::Date => "\$model->{$field->name}?->toDateString()",
                FieldType::DateTime => "\$model->{$field->name}?->format('Y-m-d H:i:s')",
                default => "\$model->{$field->name}",
            }.',', $fields)),
        ];

        // Rules mention the route parameter, which is itself a placeholder.
        return array_map(fn (string $value): string => str_replace('{{ routeParameter }}', $module->routeParameter(), $value), $replacements);
    }

    /**
     * Required, optional, or (for a yes/no) optional but never empty, because its
     * column defaults to false and can't store null.
     */
    private static function presence(Field $field): string
    {
        return match (true) {
            $field->type === FieldType::Boolean => "'sometimes'",
            $field->isRequired() => "'required'",
            default => "'nullable'",
        };
    }

    /**
     * Runs Pint on the new files when it's installed, so they match the project's style.
     *
     * @param  list<string>  $paths
     */
    private function format(array $paths): void
    {
        if (! is_file(base_path('vendor/bin/pint'))) {
            return;
        }

        Process::path(base_path())->run(['php', 'vendor/bin/pint', ...$paths]);
    }
}
