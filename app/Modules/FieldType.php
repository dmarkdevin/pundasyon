<?php

declare(strict_types=1);

namespace App\Modules;

/**
 * The field types make:module understands, and what each becomes in every
 * generated file. Adding a type means adding a case and a line to each match.
 */
enum FieldType: string
{
    case String = 'string';
    case Text = 'text';
    case Integer = 'integer';
    case Decimal = 'decimal';
    case Boolean = 'boolean';
    case Date = 'date';
    case DateTime = 'datetime';
    case Json = 'json';
    case ForeignId = 'foreignId';

    public static function fromInput(string $type): ?self
    {
        return match (strtolower($type)) {
            'string' => self::String,
            'text' => self::Text,
            'integer', 'int' => self::Integer,
            'decimal', 'money', 'float' => self::Decimal,
            'boolean', 'bool' => self::Boolean,
            'date' => self::Date,
            'datetime', 'timestamp' => self::DateTime,
            'json', 'array' => self::Json,
            'foreignid' => self::ForeignId,
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_map(fn (self $type): string => $type->value, self::cases());
    }

    public function migrationColumn(Field $field): string
    {
        $column = match ($this) {
            self::String => "\$table->string('{$field->name}')",
            self::Text => "\$table->text('{$field->name}')",
            self::Integer => "\$table->integer('{$field->name}')",
            self::Decimal => "\$table->decimal('{$field->name}', 12, 2)",
            self::Boolean => "\$table->boolean('{$field->name}')->default(false)",
            self::Date => "\$table->date('{$field->name}')",
            self::DateTime => "\$table->dateTime('{$field->name}')",
            self::Json => "\$table->json('{$field->name}')",
            self::ForeignId => "\$table->foreignId('{$field->name}')",
        };

        if ($field->nullable && $this !== self::Boolean) {
            $column .= '->nullable()';
        }

        if ($field->unique) {
            $column .= '->unique()';
        }

        if ($this === self::ForeignId) {
            $column .= "->constrained('{$field->relatedTable()}')".($field->nullable ? '->nullOnDelete()' : '->restrictOnDelete()');
        }

        return $column.';';
    }

    public function cast(): ?string
    {
        return match ($this) {
            self::Integer, self::ForeignId => 'integer',
            self::Decimal => 'decimal:2',
            self::Boolean => 'boolean',
            self::Date => 'date',
            self::DateTime => 'datetime',
            self::Json => 'array',
            default => null,
        };
    }

    /**
     * A believable fake value, as PHP code for the factory.
     */
    public function factoryValue(Field $field): string
    {
        return match ($this) {
            self::String => match (true) {
                str_contains($field->name, 'email') => 'fake()->unique()->safeEmail()',
                str_contains($field->name, 'phone') => 'fake()->phoneNumber()',
                str_contains($field->name, 'url') => 'fake()->url()',
                $field->name === 'name', $field->name === 'title' => 'fake()->unique()->words(3, true)',
                default => $field->unique ? 'fake()->unique()->bothify(\'??-#####\')' : 'fake()->words(3, true)',
            },
            self::Text => 'fake()->paragraph()',
            self::Integer => 'fake()->numberBetween(0, 1000)',
            self::Decimal => 'fake()->randomFloat(2, 1, 10000)',
            self::Boolean => 'fake()->boolean()',
            self::Date => 'fake()->dateTimeBetween(\'-1 year\', \'+1 year\')->format(\'Y-m-d\')',
            self::DateTime => 'fake()->dateTimeBetween(\'-1 year\', \'+1 year\')->format(\'Y-m-d H:i:s\')',
            self::Json => '[\'note\' => fake()->sentence()]',
            self::ForeignId => '\\'.$field->relatedModelClass().'::factory()',
        };
    }

    /**
     * Validation rules after required/nullable, as PHP code.
     *
     * @return list<string>
     */
    public function rules(Field $field, string $table): array
    {
        $rules = match ($this) {
            self::String => ["'string'", "'max:255'"],
            self::Text => ["'string'", "'max:65535'"],
            self::Integer => ["'integer'", "'min:-2147483648'", "'max:2147483647'"],
            self::Decimal => ["'numeric'", "'min:-9999999999.99'", "'max:9999999999.99'"],
            self::Boolean => ["'boolean'"],
            self::Date, self::DateTime => ["'date'"],
            self::Json => ["'array'", "'max:100'"],
            self::ForeignId => ["'integer'", "Rule::exists('{$field->relatedTable()}', 'id')"],
        };

        if ($field->unique) {
            $rules[] = "Rule::unique('{$table}', '{$field->name}')->ignore(\$this->route('{{ routeParameter }}'))";
        }

        return $rules;
    }

    public function formComponent(Field $field): string
    {
        $label = "->label('{$field->label()}')";
        $required = $field->isRequired() ? '->required()' : '';
        // Checked in the form too, so a duplicate shows as a message instead of a database error.
        $required .= $field->unique ? '->unique(ignoreRecord: true)' : '';

        return match ($this) {
            self::String => "TextInput::make('{$field->name}'){$label}{$required}->maxLength(255)".(str_contains($field->name, 'email') ? '->email()' : ''),
            self::Text => "Textarea::make('{$field->name}'){$label}{$required}->rows(4)->columnSpanFull()",
            self::Integer => "TextInput::make('{$field->name}'){$label}{$required}->integer()",
            self::Decimal => "TextInput::make('{$field->name}'){$label}{$required}->numeric()->step('0.01')",
            self::Boolean => "Toggle::make('{$field->name}'){$label}",
            self::Date => "DatePicker::make('{$field->name}'){$label}{$required}",
            self::DateTime => "DateTimePicker::make('{$field->name}'){$label}{$required}",
            self::Json => "KeyValue::make('{$field->name}'){$label}{$required}->columnSpanFull()",
            self::ForeignId => "Select::make('{$field->name}'){$label}{$required}->relationship('{$field->relationName()}', '{$field->relatedTitleAttribute()}')->searchable()->preload()",
        };
    }

    public function tableColumn(Field $field, bool $isTitle): string
    {
        $label = "->label('{$field->label()}')";

        return match ($this) {
            self::String => "TextColumn::make('{$field->name}'){$label}->searchable()->sortable()".($isTitle ? '' : '->toggleable()'),
            self::Text => "TextColumn::make('{$field->name}'){$label}->limit(50)->toggleable(isToggledHiddenByDefault: true)",
            self::Integer, self::Decimal => "TextColumn::make('{$field->name}'){$label}->numeric(".($this === self::Decimal ? 'decimalPlaces: 2' : '').')->sortable()',
            self::Boolean => "IconColumn::make('{$field->name}'){$label}->boolean()->sortable()",
            self::Date => "TextColumn::make('{$field->name}'){$label}->date()->sortable()",
            self::DateTime => "TextColumn::make('{$field->name}'){$label}->dateTime()->sortable()",
            self::Json => "TextColumn::make('{$field->name}'){$label}->badge()->toggleable(isToggledHiddenByDefault: true)",
            self::ForeignId => "TextColumn::make('{$field->relationName()}.{$field->relatedTitleAttribute()}'){$label}->sortable()",
        };
    }

    public function tableFilter(Field $field): ?string
    {
        return match ($this) {
            self::Boolean => "TernaryFilter::make('{$field->name}')->label('{$field->label()}')",
            self::ForeignId => "SelectFilter::make('{$field->name}')->label('{$field->label()}')->relationship('{$field->relationName()}', '{$field->relatedTitleAttribute()}')->searchable()->preload()",
            default => null,
        };
    }

    public function infolistEntry(Field $field): string
    {
        $label = "->label('{$field->label()}')";

        return match ($this) {
            self::Boolean => "IconEntry::make('{$field->name}'){$label}->boolean()",
            self::Date => "TextEntry::make('{$field->name}'){$label}->date()->placeholder('-')",
            self::DateTime => "TextEntry::make('{$field->name}'){$label}->dateTime()->placeholder('-')",
            self::Decimal => "TextEntry::make('{$field->name}'){$label}->numeric(decimalPlaces: 2)->placeholder('-')",
            self::Json => "KeyValueEntry::make('{$field->name}'){$label}->columnSpanFull()",
            self::Text => "TextEntry::make('{$field->name}'){$label}->placeholder('-')->columnSpanFull()",
            self::ForeignId => "TextEntry::make('{$field->relationName()}.{$field->relatedTitleAttribute()}'){$label}->placeholder('-')",
            default => "TextEntry::make('{$field->name}'){$label}->placeholder('-')",
        };
    }

    /**
     * How the API resource shows the value.
     */
    public function resourceValue(Field $field): string
    {
        return match ($this) {
            self::Date => "\$this->{$field->name}?->toDateString()",
            self::DateTime => "\$this->{$field->name}?->toIso8601String()",
            default => "\$this->{$field->name}",
        };
    }

    /**
     * How the API list filters by this field: an exact match or a partial one.
     */
    public function apiFilter(Field $field): ?string
    {
        return match ($this) {
            self::String => "AllowedFilter::partial('{$field->name}')",
            self::Integer, self::Boolean, self::ForeignId, self::Date => "AllowedFilter::exact('{$field->name}')",
            default => null,
        };
    }

    public function isSortable(): bool
    {
        return ! in_array($this, [self::Text, self::Json], true);
    }

    /**
     * PHPDoc type for the model's @property line.
     */
    public function phpDocType(): string
    {
        return match ($this) {
            self::String, self::Text, self::Decimal => 'string',
            self::Integer, self::ForeignId => 'int',
            self::Boolean => 'bool',
            self::Date, self::DateTime => '\\Illuminate\\Support\\Carbon',
            self::Json => 'array<string, mixed>',
        };
    }
}
