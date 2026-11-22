<?php

declare(strict_types=1);

namespace Siberfx\RbacPermission\Filament\Resources\RoleResource\Pages;

use Filament\Resources\Pages\EditRecord;
use LogicException;
use Siberfx\RbacPermission\Filament\Resources\RoleResource;
use Siberfx\RbacPermission\Filament\Support\Operations;
use Siberfx\RbacPermission\Models\Role;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    /**
     * The rank is checked against the actor's own rank before anything is saved.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['rank'])) {
            Operations::setRank(RoleResource::requireRbacActor(), $this->role(), (int) $data['rank']);
        }

        unset($data['rank']);

        return $data;
    }

    private function role(): Role
    {
        $role = $this->getRecord();

        return $role instanceof Role ? $role : throw new LogicException('EditRole edits a role.');
    }
}
