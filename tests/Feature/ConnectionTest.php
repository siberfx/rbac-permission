<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Siberfx\RbacPermission\Facades\Rbac;
use Siberfx\RbacPermission\Models\Role;

beforeEach(function () {
    // RBAC tables in their own database, users in the default one.
    config([
        'database.connections.rbac' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true],
        'rbac.connection' => 'rbac',
        'rbac.audit.enabled' => true,
    ]);

    $default = DB::getDefaultConnection();
    DB::setDefaultConnection('rbac');
    (require __DIR__.'/../../database/migrations/create_rbac_permission_tables.php')->up();
    DB::setDefaultConnection($default);
});

test('every read and write uses the rbac connection', function () {
    $user = $this->makeUser(['name' => 'Jane']);

    Rbac::sync(['staff' => ['billing.invoice.show@own'], 'manager' => ['billing.invoice.show@team']]);
    Rbac::assign($user, 'staff');
    Rbac::assign($user, 'manager', null, Carbon::now()->subMinute());
    Rbac::allow($user, 'billing.invoice.edit@own', Carbon::now()->subMinute());

    expect(Rbac::allows('billing.invoice.show', null, $user))->toBeTrue()
        ->and(Rbac::rankOf($user))->toBe(10)         // staff, from rbac.ranks
        ->and($user->hasRole('staff'))->toBeTrue()
        ->and(Rbac::pruneExpired())->toBe(2)
        ->and(DB::connection('rbac')->table('rbac_role_assignments')->count())->toBe(1)
        ->and(DB::connection('rbac')->table('rbac_audit_logs')->count())->toBeGreaterThan(0);

    // Nothing landed in the default database.
    expect(Role::query()->getConnection()->getName())->toBe('rbac')
        ->and(DB::connection()->table('rbac_roles')->count())->toBe(0);

    Rbac::sync(['manager' => ['billing.invoice.show@team']], prune: true);

    expect(DB::connection('rbac')->table('rbac_roles')->pluck('slug')->all())->toBe(['manager']);
});
