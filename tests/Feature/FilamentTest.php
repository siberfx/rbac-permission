<?php

use Filament\Panel;
use Filament\PanelRegistry;
use Illuminate\Validation\ValidationException;
use Siberfx\RbacPermission\Exceptions\PermissionDenied;
use Siberfx\RbacPermission\Facades\Rbac;
use Siberfx\RbacPermission\Filament\Resources\RoleResource;
use Siberfx\RbacPermission\Filament\RbacPlugin;
use Siberfx\RbacPermission\Filament\Support\Operations;
use Siberfx\RbacPermission\Models\Role;
use Siberfx\RbacPermission\Models\RoleGrant;

beforeEach(function () {
    app(PanelRegistry::class)->register(Panel::make()->id('admin')->default()->plugin(RbacPlugin::make()));

    Rbac::sync(config('rbac.roles'));
    Rbac::sync(['role-editor' => ['admin.role.show@global', 'admin.role.edit@global', 'billing.invoice.show@team']]);
});

test('the plugin adds the role resource to a panel', function () {
    $panel = Panel::make()->id('admin')->plugin(RbacPlugin::make());

    expect($panel->getPlugin('rbac'))->toBeInstanceOf(RbacPlugin::class)
        ->and(collect($panel->getResources())->contains(RoleResource::class))->toBeTrue();
});

test('the role resource is visible only with admin.role.show', function () {
    $admin = $this->makeUser(['name' => 'Admin']);
    Rbac::assign($admin, 'admin');
    $staff = $this->makeUser(['name' => 'Staff']);
    Rbac::assign($staff, 'staff');

    $this->actingAs($admin);
    expect(RoleResource::canViewAny())->toBeTrue();

    $this->actingAs($staff);
    expect(RoleResource::canViewAny())->toBeFalse();
});

test('a role can be edited only below the editor\'s rank', function () {
    $admin = $this->makeUser(['name' => 'Admin']);
    Rbac::assign($admin, 'admin');
    $staffRole = Role::query()->where('slug', 'staff')->firstOrFail();
    $adminRole = Role::query()->where('slug', 'admin')->firstOrFail();

    $this->actingAs($admin);
    expect(RoleResource::canEdit($staffRole))->toBeTrue()
        ->and(RoleResource::canEdit($adminRole))->toBeFalse();
});

test('creating and deleting roles from the screen is disabled', function () {
    $staffRole = Role::query()->where('slug', 'staff')->firstOrFail();

    expect(RoleResource::canCreate())->toBeFalse()
        ->and(RoleResource::canDelete($staffRole))->toBeFalse();
});

test('an editor cannot grant a permission they do not hold', function () {
    $editor = $this->makeUser(['name' => 'Editor']);
    Rbac::assign($editor, 'role-editor');
    $staffRole = Role::query()->where('slug', 'staff')->firstOrFail();

    expect(fn () => Operations::grant($editor, $staffRole, 'billing.invoice.delete', 'own'))
        ->toThrow(PermissionDenied::class)
        ->and($staffRole->grants()->where('permission', 'billing.invoice.delete')->exists())->toBeFalse();
});

test('an editor cannot grant a wider scope than they hold', function () {
    $editor = $this->makeUser(['name' => 'Editor']);
    Rbac::assign($editor, 'role-editor');
    $staffRole = Role::query()->where('slug', 'staff')->firstOrFail();

    expect(fn () => Operations::grant($editor, $staffRole, 'billing.invoice.show', 'tenant'))
        ->toThrow(PermissionDenied::class);

    $grant = Operations::grant($editor, $staffRole, 'billing.invoice.show', 'team');

    expect($grant)->toBeInstanceOf(RoleGrant::class)
        ->and($staffRole->grants()->where('permission', 'billing.invoice.show')->where('scope', 'team')->exists())->toBeTrue();
});

test('a grant can be removed by an actor who holds it', function () {
    $editor = $this->makeUser(['name' => 'Editor']);
    Rbac::assign($editor, 'role-editor');
    $staffRole = Role::query()->where('slug', 'staff')->firstOrFail();
    $grant = Operations::grant($editor, $staffRole, 'billing.invoice.show', 'team');

    Operations::revoke($editor, $staffRole, $grant);

    expect($staffRole->grants()->where('permission', 'billing.invoice.show')->where('scope', 'team')->exists())->toBeFalse();
});

test('the rank of a role cannot be set at or above the actor\'s rank', function () {
    $admin = $this->makeUser(['name' => 'Admin']);
    Rbac::assign($admin, 'admin');             // rank 80
    $staffRole = Role::query()->where('slug', 'staff')->firstOrFail();

    expect(fn () => Operations::setRank($admin, $staffRole, 80))->toThrow(ValidationException::class);

    Operations::setRank($admin, $staffRole, 60);

    expect($staffRole->refresh()->rank)->toBe(60);
});

test('assigning a role from the user screen follows the rank rules', function () {
    $admin = $this->makeUser(['name' => 'Admin']);
    Rbac::assign($admin, 'admin');
    $target = $this->makeUser(['name' => 'Target']);

    expect(fn () => Operations::assignRole($admin, $target, 'admin'))->toThrow(ValidationException::class);

    Operations::assignRole($admin, $target, 'manager');

    expect($target->refresh()->hasRole('manager'))->toBeTrue();

    Operations::unassignRole($admin, $target, 'manager');

    expect($target->refresh()->hasRole('manager'))->toBeFalse();
});
