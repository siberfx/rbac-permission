<?php

use Siberfx\RbacPermission\Facades\Rbac;
use Siberfx\RbacPermission\Models\Role;

beforeEach(function () {
    Rbac::sync(config('rbac.roles'));
    Rbac::sync([
        'role-editor' => ['admin.role.show@global', 'admin.role.edit@global', 'billing.invoice.show@team'],
    ]);
});

test('the roles page lists the roles of the guard', function () {
    $admin = $this->makeUser(['name' => 'Admin']);
    Rbac::assign($admin, 'admin');

    $this->actingAs($admin)
        ->get('/rbac/roles')
        ->assertOk()
        ->assertSee('manager')
        ->assertSee('staff');
});

test('a user without admin.role.show gets 403', function () {
    $staff = $this->makeUser(['name' => 'Staff']);
    Rbac::assign($staff, 'staff');

    $this->actingAs($staff)->get('/rbac/roles')->assertForbidden();
});

test('an admin can add a grant they hold', function () {
    $admin = $this->makeUser(['name' => 'Admin']);
    Rbac::assign($admin, 'admin');
    $staffRole = Role::query()->where('slug', 'staff')->firstOrFail();

    $this->actingAs($admin)
        ->post("/rbac/roles/{$staffRole->id}/grants", ['permission' => 'billing.invoice.show@tenant'])
        ->assertRedirect();

    expect($staffRole->grants()->where('permission', 'billing.invoice.show')->where('scope', 'tenant')->exists())->toBeTrue();
});

test('an admin can remove a grant', function () {
    $admin = $this->makeUser(['name' => 'Admin']);
    Rbac::assign($admin, 'admin');
    $staffRole = Role::query()->where('slug', 'staff')->firstOrFail();

    $this->actingAs($admin)
        ->delete("/rbac/roles/{$staffRole->id}/grants", ['permission' => 'billing.invoice.edit@own'])
        ->assertRedirect();

    expect($staffRole->grants()->where('permission', 'billing.invoice.edit')->exists())->toBeFalse();
});

test('a role editor cannot grant a permission they do not hold', function () {
    $editor = $this->makeUser(['name' => 'Editor']);
    Rbac::assign($editor, 'role-editor');
    $staffRole = Role::query()->where('slug', 'staff')->firstOrFail();

    $this->actingAs($editor)
        ->post("/rbac/roles/{$staffRole->id}/grants", ['permission' => 'billing.invoice.delete@own'])
        ->assertForbidden();

    expect($staffRole->grants()->where('permission', 'billing.invoice.delete')->exists())->toBeFalse();
});

test('a role editor cannot grant a wider scope than they hold', function () {
    $editor = $this->makeUser(['name' => 'Editor']);
    Rbac::assign($editor, 'role-editor');   // holds billing.invoice.show at team only
    $staffRole = Role::query()->where('slug', 'staff')->firstOrFail();

    $this->actingAs($editor)
        ->post("/rbac/roles/{$staffRole->id}/grants", ['permission' => 'billing.invoice.show@tenant'])
        ->assertForbidden();

    $this->actingAs($editor)
        ->post("/rbac/roles/{$staffRole->id}/grants", ['permission' => 'billing.invoice.show@team'])
        ->assertRedirect();
});

test('an admin can assign a role below their rank from the user page', function () {
    $admin = $this->makeUser(['name' => 'Admin']);
    Rbac::assign($admin, 'admin');
    $target = $this->makeUser(['name' => 'Target']);

    $this->actingAs($admin)
        ->post("/rbac/users/{$target->getKey()}/roles", ['role' => 'manager'])
        ->assertRedirect();

    expect($target->refresh()->hasRole('manager'))->toBeTrue();
});

test('an admin cannot assign a role at their own rank', function () {
    $admin = $this->makeUser(['name' => 'Admin']);
    Rbac::assign($admin, 'admin');
    $target = $this->makeUser(['name' => 'Target']);

    $this->actingAs($admin)
        ->post("/rbac/users/{$target->getKey()}/roles", ['role' => 'admin'])
        ->assertSessionHasErrors('role');

    expect($target->refresh()->roles)->toHaveCount(0);
});
