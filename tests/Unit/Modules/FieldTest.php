<?php

declare(strict_types=1);

use App\Modules\Field;
use App\Modules\FieldType;

it('reads name, type and options', function (): void {
    $field = Field::parse('sku:string:unique');

    expect($field->name)->toBe('sku')
        ->and($field->type)->toBe(FieldType::String)
        ->and($field->unique)->toBeTrue()
        ->and($field->nullable)->toBeFalse()
        ->and(Field::parse('notes:text:nullable')->isRequired())->toBeFalse();
});

it('accepts common type spellings', function (string $input, FieldType $type): void {
    expect(Field::parse("value:{$input}")->type)->toBe($type);
})->with([
    ['int', FieldType::Integer],
    ['money', FieldType::Decimal],
    ['bool', FieldType::Boolean],
    ['timestamp', FieldType::DateTime],
    ['array', FieldType::Json],
]);

it('works out relations from a foreign key', function (): void {
    $field = Field::parse('invoice_line_id:foreignId');

    expect($field->relationName())->toBe('invoiceLine')
        ->and($field->relatedModel())->toBe('InvoiceLine')
        ->and($field->relatedTable())->toBe('invoice_lines')
        ->and($field->label())->toBe('Invoice line');
});

it('explains what is wrong with a field', function (string $definition, string $message): void {
    expect(fn () => Field::parse($definition))->toThrow(InvalidArgumentException::class, $message);
})->with([
    ['Name:string', 'snake_case'],
    ['name', 'needs a type'],
    ['name:colour', 'needs a type'],
    ['id:integer', 'added automatically'],
    ['category:foreignId', 'must end in _id'],
    ['name:string:required', 'Unknown option'],
]);

it('never makes a yes/no field required', function (): void {
    expect(Field::parse('is_active:boolean')->isRequired())->toBeFalse();
});
