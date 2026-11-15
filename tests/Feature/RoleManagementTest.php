<?php

use Siberfx\RbacPermission\Facades\Rbac;
use Siberfx\RbacPermission\Models\Role;

test('sync writes config roles and replaces their grants', function () {
    config(['rbac.roles' => [
        'manager' => ['billing.invoice.show@team', 'billing.invoice.edit@team'],
    ]]);

    $first = Rbac::sync(config('rbac.roles'));
    expect($first)->toMatchArray(['roles' => 1, 'grants' => 2]);

    config(['rbac.roles' => ['manager' => ['billing.invoice.show@team']]]);
    Rbac::sync(config('rbac.roles'));

    expect(Role::query()->where('slug', 'manager')->first()->grants)->toHaveCount(1);
});

test('sync validates every grant before writing anything', function () {
    $before = Role::query()->count();

    expect(fn () => Rbac::sync(['broken' => ['billing.invoice.fly@own']]))
        ->toThrow(\Siberfx\RbacPermission\Exceptions\InvalidPermission::class);

    expect(Role::query()->count())->toBe($before);
});

test('prune removes roles that are no longer in config', function () {
    Rbac::role('legacy');
    Rbac::sync(['manager' => ['billing.invoice.show@team']], prune: true);

    expect(Role::query()->where('slug', 'legacy')->exists())->toBeFalse()
        ->and(Role::query()->where('slug', 'manager')->exists())->toBeTrue();
});

test('the matrix reports the widest scope each role holds per permission', function () {
    Rbac::sync([
        'staff' => ['billing.invoice.show@own'],
        'manager' => ['billing.invoice.show@team', 'billing.*@tenant'],
    ]);

    $matrix = Rbac::matrix();

    expect($matrix['roles'])->toBe(['manager', 'staff'])
        ->and($matrix['rows']['billing.invoice.show'])->toBe(['manager' => 'tenant', 'staff' => 'own'])
        ->and($matrix['rows']['admin.user.delete'])->toBe(['manager' => null, 'staff' => null]);
});

test('the rbac:matrix command prints the grid', function () {
    Rbac::sync(['staff' => ['billing.invoice.show@own']]);

    $this->artisan('rbac:matrix --only-granted')
        ->expectsOutputToContain('billing.invoice.show')
        ->assertExitCode(0);
});

test('the rbac:explain command exits 0 when allowed and 1 when denied', function () {
    $user = $this->makeUser(['name' => 'Jane'], [10]);
    Rbac::sync(['staff' => ['billing.invoice.show@own']]);
    $user->assignRole('staff');

    // Without a subject, any own grant counts (the list page check).
    $this->artisan("rbac:explain billing.invoice.show {$user->id}")->assertExitCode(0);
    // Edit is not granted at all.
    $this->artisan("rbac:explain billing.invoice.edit {$user->id}")->assertExitCode(1);
});

test('rbac:assign and rbac:grant write through the same API', function () {
    $user = $this->makeUser(['name' => 'Jane'], [10]);

    $this->artisan('rbac:grant staff billing.invoice.show@own')->assertExitCode(0);
    $this->artisan("rbac:assign staff {$user->id}")->assertExitCode(0);

    expect(Rbac::allows('billing.invoice.show', null, $user))->toBeTrue();
});
