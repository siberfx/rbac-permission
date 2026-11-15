<?php

namespace Siberfx\RbacPermission\Filament\Resources\RoleResource\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Siberfx\RbacPermission\Filament\Concerns\ChecksRbacAccess;
use Siberfx\RbacPermission\Filament\Support\Operations;
use Siberfx\RbacPermission\Models\RoleGrant;

/**
 * The grants of one role. Adding or removing a grant needs admin.role.edit, and the
 * actor must hold the grant at that scope or wider.
 */
class GrantsRelationManager extends RelationManager
{
    use ChecksRbacAccess;

    protected static string $relationship = 'grants';

    protected static ?string $recordTitleAttribute = 'permission';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return static::rbacAllows('admin.role.show');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('permission')
                ->required()
                ->placeholder('billing.invoice.show')
                ->helperText('Module.resource.verb, wildcards allowed (for example billing.*).'),
            Select::make('scope')
                ->options(array_combine(
                    array_keys(config('rbac.scopes', [])),
                    array_keys(config('rbac.scopes', [])),
                ))
                ->default(config('rbac.default_scope', 'own'))
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('permission'),
                TextColumn::make('scope'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add grant')
                    ->visible(fn (): bool => static::rbacAllows('admin.role.edit'))
                    ->using(fn (array $data): RoleGrant => Operations::grant(
                        static::requireRbacActor(),
                        $this->getOwnerRecord(),
                        (string) $data['permission'],
                        (string) $data['scope'],
                    )),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->visible(fn (): bool => static::rbacAllows('admin.role.edit'))
                    ->action(fn (RoleGrant $record) => Operations::revoke(
                        static::requireRbacActor(),
                        $this->getOwnerRecord(),
                        $record,
                    )),
            ]);
    }
}
