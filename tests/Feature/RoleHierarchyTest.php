<?php

use Siberfx\RbacPermission\Exceptions\RoleAssignmentDenied;
use Siberfx\RbacPermission\Facades\Rbac;
use Siberfx\RbacPermission\Models\Role;

beforeEach(function () {
    config(['rbac.ranks' => ['super-admin' => 100, 'admin' => 80, 'manager' => 50, 'staff' => 10]]);
    Rbac::sync(['super-admin' => ['*@global'], 'admin' => ['billing.*@tenant'], 'manager' => ['billing.invoice.show@team'], 'staff' => ['billing.invoice.show@own']]);
});

test('sync writes ranks from config', function () {
    expect(Role::query()->where('slug', 'admin')->value('rank'))->toBe(80)
        ->and(Role::query()->where('slug', 'staff')->value('rank'))->toBe(10);
});

test('an admin may assign roles below their own rank', function () {
    $admin = $this->makeUser(['name' => 'Admin']);
    $target = $this->makeUser(['name' => 'Target']);
    Rbac::assign($admin, 'admin');

    Rbac::assign($target, 'manager', $admin);
    Rbac::assign($target, 'staff', $admin);

    expect($target->refresh()->roles->pluck('slug')->sort()->values()->all())->toBe(['manager', 'staff']);
});

test('an admin may not assign a role at or above their own rank', function () {
    $admin = $this->makeUser(['name' => 'Admin']);
    $target = $this->makeUser(['name' => 'Target']);
    Rbac::assign($admin, 'admin');

    expect(fn () => Rbac::assign($target, 'admin', $admin))->toThrow(RoleAssignmentDenied::class)
        ->and(fn () => Rbac::assign($target, 'super-admin', $admin))->toThrow(RoleAssignmentDenied::class)
        ->and($target->refresh()->roles)->toHaveCount(0);
});

test('a user who holds no role cannot assign any role', function () {
    $nobody = $this->makeUser(['name' => 'Nobody']);
    $target = $this->makeUser(['name' => 'Target']);

    expect(fn () => Rbac::assign($target, 'staff', $nobody))->toThrow(RoleAssignmentDenied::class);
});

test('removing a role follows the same rule', function () {
    $manager = $this->makeUser(['name' => 'Manager']);
    $admin = $this->makeUser(['name' => 'Admin']);
    $superAdmin = $this->makeUser(['name' => 'Root']);
    $target = $this->makeUser(['name' => 'Target']);
    Rbac::assign($manager, 'manager');
    Rbac::assign($admin, 'admin');
    Rbac::assign($superAdmin, 'super-admin');
    Rbac::assign($target, 'admin');

    expect(fn () => Rbac::unassign($target, 'admin', $manager))->toThrow(RoleAssignmentDenied::class)
        ->and(fn () => Rbac::unassign($target, 'admin', $admin))->toThrow(RoleAssignmentDenied::class)
        ->and(Rbac::unassign($target, 'admin', $superAdmin))->toBe(1);
});

test('assignments without an actor are not restricted', function () {
    $target = $this->makeUser(['name' => 'Target']);

    Rbac::assign($target, 'super-admin');

    expect($target->refresh()->hasRole('super-admin'))->toBeTrue();
});

test('rankOf returns the highest rank the user holds', function () {
    $user = $this->makeUser(['name' => 'Jane']);
    expect(Rbac::rankOf($user))->toBeNull();

    Rbac::assign($user, 'staff');
    Rbac::assign($user, 'manager');

    expect(Rbac::rankOf($user))->toBe(50);
});
