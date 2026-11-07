<?php

declare(strict_types=1);

namespace App\Modules;

use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Everything make:module needs to know about one module, worked out from the
 * name and --fields: class names, table, permission names and the fields.
 */
final readonly class ModuleDefinition
{
    /** Names the app already uses. */
    private const RESERVED = ['User', 'Role', 'Permission', 'LoginEvent', 'Activity', 'Setting', 'Settings', 'Module', 'Model', 'Auth', 'Api'];

    /**
     * @param  list<Field>  $fields
     */
    public function __construct(
        public string $model,
        public array $fields,
        public bool $softDeletes = true,
        public bool $api = true,
        public bool $admin = true,
        public bool $tests = true,
    ) {}

    public static function make(string $name, string $fields, bool $softDeletes, bool $api, bool $admin, bool $tests): self
    {
        $model = Str::studly(Str::singular(trim($name)));

        if (preg_match('/^[A-Z][A-Za-z0-9]*$/', $model) !== 1) {
            throw new InvalidArgumentException("\"{$name}\" isn't a valid module name. Use a singular word in StudlyCase, such as Product or InvoiceLine.");
        }

        if (in_array($model, self::RESERVED, true)) {
            throw new InvalidArgumentException("{$model} is already used by the app. Pick another name.");
        }

        $parsed = array_values(array_map(Field::parse(...), array_filter(array_map(trim(...), explode(',', $fields)), fn (string $field): bool => $field !== '')));

        if ($parsed === []) {
            throw new InvalidArgumentException('Give at least one field, for example --fields="name:string,price:decimal".');
        }

        $names = array_map(fn (Field $field): string => $field->name, $parsed);

        if (count($names) !== count(array_unique($names))) {
            throw new InvalidArgumentException('Each field name can only be used once.');
        }

        return new self($model, $parsed, $softDeletes, $api, $admin, $tests);
    }

    public function models(): string
    {
        return Str::pluralStudly($this->model);
    }

    public function table(): string
    {
        return Str::snake($this->models());
    }

    /** "products" for Product, "invoice-lines" for InvoiceLine: the URL and permission prefix. */
    public function uri(): string
    {
        return Str::kebab($this->models());
    }

    public function permission(): string
    {
        return $this->table();
    }

    public function variable(): string
    {
        return Str::camel($this->model);
    }

    public function routeParameter(): string
    {
        return Str::snake($this->model);
    }

    public function label(): string
    {
        return Str::lower(Str::headline($this->model));
    }

    public function pluralLabel(): string
    {
        return Str::lower(Str::headline($this->models()));
    }

    /**
     * @return list<string>
     */
    public function actions(): array
    {
        return $this->softDeletes ? ['view', 'create', 'update', 'delete', 'restore'] : ['view', 'create', 'update', 'delete'];
    }

    /**
     * The field shown as a record's name: the first text field, otherwise the id.
     */
    public function titleField(): string
    {
        foreach ($this->fields as $field) {
            if ($field->type === FieldType::String) {
                return $field->name;
            }
        }

        return 'id';
    }

    /**
     * @return list<Field>
     */
    public function foreignKeys(): array
    {
        return array_values(array_filter($this->fields, fn (Field $field): bool => $field->type === FieldType::ForeignId));
    }

    public function firstUniqueField(): ?Field
    {
        foreach ($this->fields as $field) {
            if ($field->unique) {
                return $field;
            }
        }

        return null;
    }

    public function firstRequiredField(): ?Field
    {
        foreach ($this->fields as $field) {
            if ($field->isRequired()) {
                return $field;
            }
        }

        return null;
    }
}
