<?php

declare(strict_types=1);

use Siberfx\RbacPermission\Facades\Rbac;

beforeEach(function () {
    Rbac::sync(['staff' => ['billing.invoice.show@own', 'billing.invoice.edit@own']]);
    $this->user = $this->makeUser(['name' => 'Jane']);
    $this->user->assignRole('staff');
});

test('permissionsOf() lists every allowed permission with its widest scope', function () {
    Rbac::allow($this->user, 'billing.invoice.show@team');
    Rbac::allow($this->user, 'reports.sales.*@tenant');

    expect(Rbac::permissionsOf($this->user))->toBe([
        'billing.invoice.show' => 'team',
        'billing.invoice.edit' => 'own',
        'reports.sales.access' => 'tenant',
        'reports.sales.show' => 'tenant',
        'reports.sales.export' => 'tenant',
    ]);
});

test('a global deny removes a permission; a scoped deny does not', function () {
    Rbac::deny($this->user, 'billing.invoice.edit@global');
    Rbac::deny($this->user, 'billing.invoice.show@team');

    // Same rule as allows() without a subject: only a global deny hides an action entirely.
    expect(Rbac::permissionsOf($this->user))->toBe(['billing.invoice.show' => 'own']);
});

test('a user without grants, or no user, has no permissions', function () {
    expect(Rbac::permissionsOf($this->makeUser(['name' => 'Nobody'])))->toBe([])
        ->and(Rbac::permissionsOf())->toBe([]);
});

test('the user model exposes the same list', function () {
    $this->actingAs($this->user);

    expect($this->user->rbacPermissions())->toBe(Rbac::permissionsOf($this->user))
        ->and(array_keys($this->user->rbacPermissions()))->toBe(['billing.invoice.show', 'billing.invoice.edit']);
});
