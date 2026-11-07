<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages;

use App\Actions\Activity\LogAccessChange;
use App\Filament\Resources\Users\UserActions;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Spatie\Permission\PermissionRegistrar;
use STS\FilamentImpersonate\Actions\Impersonate;

final class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        $isSelf = fn (User $record): bool => UserActions::isSelf($record);

        return [
            Impersonate::make()->record($this->getRecord()),
            UserActions::suspend(),
            UserActions::activate(),
            DeleteAction::make()->hidden($isSelf),
            RestoreAction::make(),
            ForceDeleteAction::make()->hidden($isSelf),
        ];
    }

    /** @var array{roles: list<string>, permissions: list<string>} */
    private array $before = ['roles' => [], 'permissions' => []];

    protected function beforeSave(): void
    {
        $this->before = $this->access();
    }

    /**
     * Roles and direct permissions live in pivot tables, so changes are logged here.
     */
    protected function afterSave(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $after = $this->access();
        LogAccessChange::between($this->getRecord(), 'users', $this->before['roles'], $after['roles']);
        LogAccessChange::between($this->getRecord(), 'users', $this->before['permissions'], $after['permissions'], 'Changed direct permissions');
    }

    /**
     * @return array{roles: list<string>, permissions: list<string>}
     */
    private function access(): array
    {
        $record = $this->getRecord();

        if (! $record instanceof User) {
            return ['roles' => [], 'permissions' => []];
        }

        $record->unsetRelation('roles')->unsetRelation('permissions');

        return [
            'roles' => array_values(array_map(strval(...), $record->roles()->pluck('name')->all())),
            'permissions' => array_values(array_map(strval(...), $record->permissions()->pluck('name')->all())),
        ];
    }
}
