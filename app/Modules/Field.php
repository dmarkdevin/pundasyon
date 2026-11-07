<?php

declare(strict_types=1);

namespace App\Modules;

use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * One field from --fields, written "name:type" with optional ":nullable" and ":unique",
 * for example "sku:string:unique" or "notes:text:nullable".
 */
final readonly class Field
{
    /** Columns every generated table already has. */
    private const RESERVED = ['id', 'created_at', 'updated_at', 'deleted_at'];

    public function __construct(
        public string $name,
        public FieldType $type,
        public bool $nullable = false,
        public bool $unique = false,
    ) {}

    public static function parse(string $definition): self
    {
        $parts = array_map(trim(...), explode(':', trim($definition)));
        $name = $parts[0];

        if (preg_match('/^[a-z][a-z0-9_]*$/', $name) !== 1) {
            throw new InvalidArgumentException("\"{$name}\" isn't a valid field name. Use lowercase snake_case, such as unit_price.");
        }

        if (in_array($name, self::RESERVED, true)) {
            throw new InvalidArgumentException("\"{$name}\" is added automatically, so leave it out of --fields.");
        }

        $type = FieldType::fromInput($parts[1] ?? '');

        if ($type === null) {
            throw new InvalidArgumentException("\"{$definition}\" needs a type. Use one of: ".implode(', ', FieldType::names()).'.');
        }

        $modifiers = array_slice($parts, 2);
        $unknown = array_diff($modifiers, ['nullable', 'unique']);

        if ($unknown !== []) {
            throw new InvalidArgumentException('Unknown option "'.implode('", "', $unknown)."\" on {$name}. Use nullable or unique.");
        }

        if ($type === FieldType::ForeignId && ! str_ends_with($name, '_id')) {
            throw new InvalidArgumentException("{$name} is a foreignId, so its name must end in _id, such as category_id.");
        }

        return new self($name, $type, in_array('nullable', $modifiers, true), in_array('unique', $modifiers, true));
    }

    public function label(): string
    {
        $words = $this->type === FieldType::ForeignId ? Str::beforeLast($this->name, '_id') : $this->name;

        // Sentence case, like the rest of the app: "Is active", "Released on".
        return Str::ucfirst(str_replace('_', ' ', $words));
    }

    /** A boolean always has a value (false by default), so it's never "required". */
    public function isRequired(): bool
    {
        return ! $this->nullable && $this->type !== FieldType::Boolean;
    }

    public function relationName(): string
    {
        return Str::camel(Str::beforeLast($this->name, '_id'));
    }

    public function relatedModel(): string
    {
        return Str::studly(Str::beforeLast($this->name, '_id'));
    }

    /**
     * @return class-string
     */
    public function relatedModelClass(): string
    {
        /** @var class-string */
        return 'App\\Models\\'.$this->relatedModel();
    }

    public function relatedTable(): string
    {
        return Str::snake(Str::pluralStudly($this->relatedModel()));
    }

    /**
     * The column shown for the related record: "name" for users, the title
     * field recorded for generated modules, otherwise the id.
     */
    public function relatedTitleAttribute(): string
    {
        return ModuleRegistry::titleAttributeFor($this->relatedModel());
    }
}
