<?php

use Siberfx\RbacPermission\Exceptions\PermissionDenied;
use Siberfx\RbacPermission\Facades\Rbac;
use Siberfx\RbacPermission\Tests\Fixtures\Invoice;

beforeEach(function () {
    // Tenant 1: Jane (team 10) owns invoice A in team 10.
    $this->jane = $this->makeUser(['name' => 'Jane'], [10]);
    $this->invoice = $this->makeInvoice(['user_id' => $this->jane->id, 'team_id' => 10, 'tenant_id' => 1]);
});

test('an own grant allows the owner and nobody else', function () {
    Rbac::role('staff');
    Rbac::grant('staff', 'billing.invoice.edit@own');
    $this->jane->assignRole('staff');

    $other = $this->makeUser(['name' => 'Omar'], [20]);
    $other->assignRole('staff');

    expect(Rbac::allows('billing.invoice.edit', $this->invoice, $this->jane))->toBeTrue()
        ->and(Rbac::allows('billing.invoice.edit', $this->invoice, $other))->toBeFalse();
});

test('a team grant reaches records of the users own teams only', function () {
    Rbac::role('manager');
    Rbac::grant('manager', 'billing.invoice.show@team');

    $teammate = $this->makeUser(['name' => 'Tam'], [10]);
    $outsider = $this->makeUser(['name' => 'Ola'], [20]);
    $teammate->assignRole('manager');
    $outsider->assignRole('manager');

    expect(Rbac::allows('billing.invoice.show', $this->invoice, $teammate))->toBeTrue()
        ->and(Rbac::allows('billing.invoice.show', $this->invoice, $outsider))->toBeFalse();
});

test('a tenant grant reaches the whole tenant but not other tenants', function () {
    Rbac::role('admin');
    Rbac::grant('admin', 'billing.*@tenant');

    $admin = $this->makeUser(['name' => 'Ada', 'tenant_id' => 1], [30]);
    $foreign = $this->makeUser(['name' => 'Fay', 'tenant_id' => 2], [40]);
    $admin->assignRole('admin');
    $foreign->assignRole('admin');

    expect(Rbac::allows('billing.invoice.delete', $this->invoice, $admin))->toBeTrue()
        ->and(Rbac::allows('billing.invoice.delete', $this->invoice, $foreign))->toBeFalse();
});

test('a global grant reaches every record', function () {
    Rbac::role('super-admin');
    Rbac::grant('super-admin', '*@global');

    $root = $this->makeUser(['name' => 'Root', 'tenant_id' => 9], [99]);
    $root->assignRole('super-admin');

    expect(Rbac::allows('billing.invoice.approve', $this->invoice, $root))->toBeTrue();
});

test('a direct deny overrides a broader role grant', function () {
    Rbac::role('admin');
    Rbac::grant('admin', 'billing.*@tenant');

    $jane = $this->jane;
    $jane->assignRole('admin');
    $jane->denyPermission('billing.invoice.delete@own');

    expect(Rbac::allows('billing.invoice.delete', $this->invoice, $jane))->toBeFalse()
        ->and(Rbac::decide('billing.invoice.delete', $this->invoice, $jane))->toBe(\Siberfx\RbacPermission\Rbac::DENY);
});

test('without a subject an own deny does not hide the whole action', function () {
    Rbac::role('staff');
    Rbac::grant('staff', 'billing.invoice.edit@own');
    $this->jane->assignRole('staff');
    $this->jane->denyPermission('billing.invoice.edit@own');

    // A list page still renders; rows are narrowed by constrain() instead.
    expect(Rbac::allows('billing.invoice.edit', null, $this->jane))->toBeTrue();
});

test('enforce throws a 403 PermissionDenied when nothing matches', function () {
    $stranger = $this->makeUser(['name' => 'Sam'], [50]);

    Rbac::enforce('billing.invoice.show', $this->invoice, $stranger);
})->throws(PermissionDenied::class, 'Missing permission [billing.invoice.show].');

test('a permission outside the catalog is rejected at check time', function () {
    Rbac::allows('billing.invoice.fly', null, $this->jane);
})->throws(\Siberfx\RbacPermission\Exceptions\InvalidPermission::class);

test('a grant that matches nothing in the catalog is rejected', function () {
    Rbac::grant('staff', 'billing.invoice.fly@own');
})->throws(\Siberfx\RbacPermission\Exceptions\InvalidPermission::class);

test('explain reports the decision and the grant that covered the subject', function () {
    Rbac::role('manager');
    Rbac::grant('manager', 'billing.invoice.show@team');
    $this->jane->assignRole('manager');

    $trace = Rbac::explain('billing.invoice.show', $this->invoice, $this->jane);

    expect($trace['decision'])->toBe('allow')
        ->and($trace['roles'])->toBe(['manager'])
        ->and($trace['grants'][0])->toMatchArray([
            'grant' => 'billing.invoice.show@team',
            'source' => 'role:manager',
            'covers' => true,
        ]);
});

test('a custom scope can be registered and used in a grant', function () {
    config(['rbac.scopes.region' => ['level' => 25, 'resolver' => RegionScopeFixture::class]]);
    app(\Siberfx\RbacPermission\Scopes\ScopeRegistry::class)
        ->register('region', RegionScopeFixture::class, 25);

    Rbac::role('regional');
    Rbac::grant('regional', 'billing.invoice.show@region');
    $this->jane->assignRole('regional');

    expect(Rbac::widestScope('billing.invoice.show', $this->jane))->toBe('region');
});

// Minimal custom scope: everything with amount under 1000 is "in region".
class RegionScopeFixture implements \Siberfx\RbacPermission\Scopes\ScopeContract
{
    public function covers(\Illuminate\Database\Eloquent\Model $user, \Illuminate\Database\Eloquent\Model $subject): bool
    {
        return (int) $subject->getAttribute('amount') < 1000;
    }

    public function constrain($query, \Illuminate\Database\Eloquent\Model $user, \Illuminate\Database\Eloquent\Model $model): void
    {
        $query->where($model->qualifyColumn('amount'), '<', 1000);
    }
}

describe('an unrestricted scope under another name', function () {
    beforeEach(function () {
        Rbac::scopes()->register('everywhere', \Siberfx\RbacPermission\Tests\Fixtures\EverywhereScope::class, 100);
        $this->user = $this->makeUser(['name' => 'Jane']);
        $this->invoice = $this->makeInvoice(['user_id' => 999, 'team_id' => 99, 'tenant_id' => 9]);
    });

    test('its deny hides list pages and every row', function () {
        Rbac::allow($this->user, 'billing.invoice.show@own');
        Rbac::deny($this->user, 'billing.invoice.show@everywhere');

        expect(Rbac::decide('billing.invoice.show', null, $this->user))->toBe('deny')
            ->and(Rbac::constrain(\Siberfx\RbacPermission\Tests\Fixtures\Invoice::query(), 'billing.invoice.show', $this->user)->count())->toBe(0);
    });

    test('its allow reaches every row without a filter', function () {
        Rbac::allow($this->user, 'billing.invoice.show@everywhere');

        expect(Rbac::constrain(\Siberfx\RbacPermission\Tests\Fixtures\Invoice::query(), 'billing.invoice.show', $this->user)->count())
            ->toBe(\Siberfx\RbacPermission\Tests\Fixtures\Invoice::query()->count());
    });
});
