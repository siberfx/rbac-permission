<?php

namespace Siberfx\RbacPermission\Filament\Resources;

use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Siberfx\RbacPermission\Facades\Rbac;
use Siberfx\RbacPermission\Filament\Concerns\ChecksRbacAccess;
use Siberfx\RbacPermission\Filament\Resources\RoleResource\Pages\EditRole;
use Siberfx\RbacPermission\Filament\Resources\RoleResource\Pages\ListRoles;
use Siberfx\RbacPermission\Filament\Resources\RoleResource\RelationManagers\GrantsRelationManager;
use Siberfx\RbacPermission\Models\Role;

/**
 * Roles of every guard. Roles are created and their slugs set by rbac:sync, so the
 * screen edits name, description and rank, and manages grants in its relation manager.
 */
class RoleResource extends Resource
{
    use ChecksRbacAccess;

    protected static ?string $model = Role::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Access control';

    protected static ?string $recordTitleAttribute = 'slug';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('description')->maxLength(255),
            TextInput::make('rank')->numeric()->integer()->minValue(0)->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('slug')->searchable(),
                TextColumn::make('guard'),
                TextColumn::make('rank')->sortable(),
                TextColumn::make('grants_count')->counts('grants')->label('Grants'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [GrantsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return static::rbacAllows('admin.role.show');
    }

    public static function canView(Model $record): bool
    {
        return static::rbacAllows('admin.role.show');
    }

    /**
     * Editing is allowed only for roles below the actor's rank, which also limits who can change them.
     */
    public static function canEdit(Model $record): bool
    {
        $actor = static::rbacActor();

        return $actor !== null
            && Rbac::allows('admin.role.edit', null, $actor)
            && Rbac::canAssign($actor, $record);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}
