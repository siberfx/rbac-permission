<?php

namespace Siberfx\RbacPermission\Filament\Resources;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Siberfx\RbacPermission\Filament\Concerns\ChecksRbacAccess;
use Siberfx\RbacPermission\Filament\Support\Operations;
use Siberfx\RbacPermission\Models\Role;

/**
 * Roles of a user, for the user resource of your admin panel. Add it to the resource's
 * relations; the owner must use the HasRbac trait.
 *
 *   public static function getRelations(): array
 *   {
 *       return [UserRolesRelationManager::class];
 *   }
 */
class UserRolesRelationManager extends RelationManager
{
    use ChecksRbacAccess;

    protected static string $relationship = 'roles';

    protected static ?string $recordTitleAttribute = 'slug';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return static::rbacAllows('admin.user.show');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('slug'),
                TextColumn::make('guard'),
                TextColumn::make('pivot.expires_at')->label('Expires')->placeholder('never'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Assign role')
                    ->visible(fn (): bool => static::rbacAllows('admin.user.edit'))
                    ->form([
                        Select::make('role')
                            ->label('Role')
                            ->options(fn (): array => Role::query()
                                ->where('guard', $this->getOwnerRecord()->rbacGuard())
                                ->orderByDesc('rank')
                                ->pluck('slug', 'slug')
                                ->all())
                            ->required(),
                        DateTimePicker::make('expires_at')
                            ->label('Expires at (optional)')
                            ->after('now'),
                    ])
                    ->using(function (array $data): Role {
                        $expiresAt = filled($data['expires_at'] ?? null) ? Carbon::parse($data['expires_at']) : null;

                        Operations::assignRole(static::requireRbacActor(), $this->getOwnerRecord(), (string) $data['role'], $expiresAt);

                        return Role::query()
                            ->where('guard', $this->getOwnerRecord()->rbacGuard())
                            ->where('slug', $data['role'])
                            ->firstOrFail();
                    }),
            ])
            ->recordActions([
                // The role row is not deleted; only the assignment is removed.
                Action::make('unassign')
                    ->label('Remove')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (): bool => static::rbacAllows('admin.user.edit'))
                    ->action(fn (Role $record) => Operations::unassignRole(
                        static::requireRbacActor(),
                        $this->getOwnerRecord(),
                        $record->slug,
                    )),
            ]);
    }
}
