<?php

declare(strict_types=1);

namespace App\Filament\Resources\Roles\Pages;

use App\Actions\Activity\LogAccessChange;
use App\Filament\Resources\Roles\RoleResource;
use App\Models\Role;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Spatie\Permission\PermissionRegistrar;

final class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->hidden(fn (Role $record): bool => $record->isSystem()),
        ];
    }

    /**
     * Saving changes a role's permissions through the pivot table, which the
     * permission cache doesn't notice on its own.
     */
    /** @var list<string> */
    private array $before = [];

    protected function beforeSave(): void
    {
        $this->before = $this->accessNames();
    }

    protected function afterSave(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        LogAccessChange::between($this->getRecord(), 'roles', $this->before, $this->accessNames());
    }

    /**
     * @return list<string>
     */
    private function accessNames(): array
    {
        $record = $this->getRecord();

        if (! $record instanceof Role) {
            return [];
        }

        $record->unsetRelation('permissions');

        return array_values(array_map(strval(...), $record->permissions()->pluck('name')->all()));
    }
}
