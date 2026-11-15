<?php

namespace Siberfx\RbacPermission\Filament\Resources\RoleResource\Pages;

use Filament\Resources\Pages\EditRecord;
use Siberfx\RbacPermission\Filament\Resources\RoleResource;
use Siberfx\RbacPermission\Filament\Support\Operations;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    /**
     * The rank is checked against the actor's own rank before anything is saved.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['rank'])) {
            Operations::setRank(RoleResource::requireRbacActor(), $this->getRecord(), (int) $data['rank']);
        }

        unset($data['rank']);

        return $data;
    }
}
