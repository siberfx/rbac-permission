<?php

declare(strict_types=1);

use Siberfx\RbacPermission\Facades\Rbac;
use Siberfx\RbacPermission\Models\Role;
use Siberfx\RbacPermission\Tests\Fixtures\User;

beforeEach(function () {
    Rbac::sync(config('rbac.roles'));
    Rbac::sync([
        'role-editor' => ['admin.role.show@global', 'admin.role.edit@global', 'billing.invoice.show@team'],
    ]);
    // Grants can only be changed on roles below the editor's rank (staff is 10).
    Role::query()->where('slug', 'role-editor')->update(['rank' => 60]);
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

test('a tenant admin cannot view or change users of another tenant', function () {
    $admin = $this->makeUser(['name' => 'Admin', 'tenant_id' => 1]);
    Rbac::assign($admin, 'admin');
    $outsider = User::create(['name' => 'Outsider', 'tenant_id' => 2]);
    Rbac::assign($outsider, 'staff');

    $this->actingAs($admin)->get("/rbac/users/{$outsider->getKey()}")->assertForbidden();
    $this->actingAs($admin)->post("/rbac/users/{$outsider->getKey()}/roles", ['role' => 'manager'])->assertForbidden();
    $this->actingAs($admin)->delete("/rbac/users/{$outsider->getKey()}/roles/staff")->assertForbidden();

    expect($outsider->refresh()->hasRole('manager'))->toBeFalse()
        ->and($outsider->hasRole('staff'))->toBeTrue();
});

test('grants can only be changed on roles below the editor\'s rank', function () {
    $admin = $this->makeUser(['name' => 'Admin']);
    Rbac::assign($admin, 'admin');                       // rank 80, holds billing.*@tenant
    $adminRole = Role::query()->where('slug', 'admin')->firstOrFail();
    $managerRole = Role::query()->where('slug', 'manager')->firstOrFail();

    // Their own role (and anything above) is off limits, in both directions.
    $this->actingAs($admin)
        ->post("/rbac/roles/{$adminRole->id}/grants", ['permission' => 'billing.invoice.show@team'])
        ->assertForbidden();
    $this->actingAs($admin)
        ->delete("/rbac/roles/{$adminRole->id}/grants", ['permission' => 'billing.*@tenant'])
        ->assertForbidden();

    expect($adminRole->grants()->where('permission', 'billing.*')->exists())->toBeTrue();

    $this->actingAs($admin)
        ->post("/rbac/roles/{$managerRole->id}/grants", ['permission' => 'billing.invoice.delete@team'])
        ->assertRedirect();
});

test('a role editor cannot hand out a permission they are denied', function () {
    $editor = $this->makeUser(['name' => 'Editor']);
    Rbac::assign($editor, 'role-editor');                // holds billing.invoice.show@team
    Rbac::deny($editor, 'billing.invoice.show@own');
    $staffRole = Role::query()->where('slug', 'staff')->firstOrFail();

    expect(Rbac::canGrant($editor, 'billing.invoice.show@team'))->toBeFalse();

    $this->actingAs($editor)
        ->post("/rbac/roles/{$staffRole->id}/grants", ['permission' => 'billing.invoice.show@team'])
        ->assertForbidden();
});
