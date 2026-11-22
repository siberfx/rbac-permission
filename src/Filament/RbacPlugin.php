<?php

declare(strict_types=1);

namespace Siberfx\RbacPermission\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Siberfx\RbacPermission\Filament\Resources\RoleResource;

/**
 * Adds the role screens to a Filament panel:
 *
 *   ->plugin(RbacPlugin::make())
 *
 * The user resource of your panel can also show the user's roles with
 * UserRolesRelationManager.
 */
final class RbacPlugin implements Plugin
{
    public static function make(): static
    {
        return new self;
    }

    public function getId(): string
    {
        return 'rbac';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([RoleResource::class]);
    }

    public function boot(Panel $panel): void {}
}
