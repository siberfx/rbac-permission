<?php

use Siberfx\RbacPermission\Facades\Rbac;
use Siberfx\RbacPermission\Models\Role;
use Siberfx\RbacPermission\Tests\Fixtures\Admin;

beforeEach(function () {
    config(['rbac.guards' => [
        'admin' => [
            'roles' => [
                'manager' => ['billing.*@tenant'],
                'auditor' => ['reports.sales.export@global'],
            ],
            'ranks' => ['manager' => 50, 'auditor' => 10],
        ],
    ]]);

    Rbac::sync(config('rbac.roles'));
    Rbac::sync(config('rbac.guards.admin.roles'), false, 'admin');
});

test('the same role slug can exist under two guards with different grants', function () {
    expect(Role::query()->where('slug', 'manager')->count())->toBe(2);

    $web = $this->makeUser(['name' => 'Web manager']);
    Rbac::assign($web, 'manager');

    $admin = Admin::create(['name' => 'Admin manager']);
    Rbac::assign($admin, 'manager');

    expect(Rbac::widestScope('billing.invoice.show', $web))->toBe('team')
        ->and(Rbac::widestScope('billing.invoice.show', $admin))->toBe('tenant');
});

test('a role from another guard cannot be assigned', function () {
    $web = $this->makeUser(['name' => 'Web user']);

    expect(fn () => Rbac::assign($web, 'auditor'))->toThrow(InvalidArgumentException::class)
        ->and($web->refresh()->roles)->toHaveCount(0);
});

test('a user only holds roles from their own guard', function () {
    $admin = Admin::create(['name' => 'Admin']);
    $admin->assignRole('auditor');

    expect($admin->hasRole('auditor'))->toBeTrue()
        ->and(Rbac::allows('reports.sales.export', null, $admin))->toBeTrue();

    // "staff" exists only under the default guard, so the admin cannot receive it.
    expect(fn () => $admin->assignRole('staff'))->toThrow(InvalidArgumentException::class)
        ->and($admin->hasRole('staff'))->toBeFalse();
});

test('rank is read from the guard of the user', function () {
    $admin = Admin::create(['name' => 'Admin']);
    Rbac::assign($admin, 'manager');

    // The default guard's "admin" role (rank 80) is not this user's role, so the rank is 50.
    expect(Rbac::rankOf($admin))->toBe(50);
});

test('a check without a user uses the guard that is logged in', function () {
    $admin = Admin::create(['name' => 'Admin']);
    Rbac::assign($admin, 'manager');

    $this->actingAs($admin, 'admin');

    expect(Rbac::allows('billing.invoice.show'))->toBeTrue()
        ->and(Rbac::widestScope('billing.invoice.show'))->toBe('tenant');
});

test('rbac:sync writes every guard', function () {
    $this->artisan('rbac:sync')
        ->expectsOutputToContain('[admin] Synced 2 role(s)')
        ->assertExitCode(0);
});

test('rbac:matrix can show the roles of one guard', function () {
    $this->artisan('rbac:matrix --guard=admin')
        ->expectsOutputToContain('auditor')
        ->assertExitCode(0);
});

test('the user model is resolved from the guard', function () {
    expect(Rbac::userModel())->toBe(\Siberfx\RbacPermission\Tests\Fixtures\User::class)
        ->and(Rbac::userModel('admin'))->toBe(Admin::class);
});

test('rbac:assign and rbac:explain accept --guard for its user model', function () {
    $admin = Admin::create(['name' => 'Admin auditor']);

    $this->artisan('rbac:assign', ['role' => 'auditor', 'user' => $admin->getKey(), '--guard' => 'admin'])->assertSuccessful();

    expect($admin->refresh()->hasRole('auditor'))->toBeTrue();

    $this->artisan('rbac:explain', ['permission' => 'reports.sales.export', 'user' => $admin->getKey(), '--guard' => 'admin'])
        ->expectsOutputToContain('ALLOW')
        ->assertSuccessful();
});

test('the admin user page manages users of another guard', function () {
    $actor = $this->makeUser(['name' => 'Root']);
    Rbac::assign($actor, 'super-admin');
    Role::query()->where('slug', 'super-admin')->update(['rank' => 100]);
    $admin = Admin::create(['name' => 'Admin auditor']);

    $this->actingAs($actor)->get("/rbac/users/{$admin->getKey()}?guard=admin")
        ->assertOk()
        ->assertSee('(admin)', false);

    $this->actingAs($actor)->post("/rbac/users/{$admin->getKey()}/roles?guard=admin", ['role' => 'auditor'])->assertRedirect();

    expect($admin->refresh()->hasRole('auditor'))->toBeTrue();

    $this->actingAs($actor)->delete("/rbac/users/{$admin->getKey()}/roles/auditor?guard=admin")->assertRedirect();

    expect($admin->refresh()->hasRole('auditor'))->toBeFalse();
});
