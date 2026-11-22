<?php

declare(strict_types=1);

use Siberfx\RbacPermission\Facades\Rbac;
use Siberfx\RbacPermission\Tests\Fixtures\Invoice;

beforeEach(function () {
    $this->jane = $this->makeUser(['name' => 'Jane', 'tenant_id' => 1], [10]);
    $this->ola = $this->makeUser(['name' => 'Ola', 'tenant_id' => 1], [20]);

    $this->mine = $this->makeInvoice(['user_id' => $this->jane->id, 'team_id' => 10, 'tenant_id' => 1]);
    $this->teamMate = $this->makeInvoice(['user_id' => $this->ola->id, 'team_id' => 10, 'tenant_id' => 1]);
    $this->olasTeam = $this->makeInvoice(['user_id' => $this->ola->id, 'team_id' => 20, 'tenant_id' => 1]);
    // Tenant 2 has its own team, so a team grant in tenant 1 must not reach it.
    $this->otherTenant = $this->makeInvoice(['user_id' => 77, 'team_id' => 90, 'tenant_id' => 2]);
});

test('an own grant lists only the users own rows', function () {
    Rbac::role('staff');
    Rbac::grant('staff', 'billing.invoice.show@own');
    $this->jane->assignRole('staff');

    $ids = Rbac::constrain(Invoice::query(), 'billing.invoice.show', $this->jane)->pluck('id')->all();

    expect($ids)->toBe([$this->mine->id]);
});

test('a team grant lists rows of the users teams', function () {
    Rbac::role('manager');
    Rbac::grant('manager', 'billing.invoice.show@team');
    $this->jane->assignRole('manager');

    $ids = Rbac::constrain(Invoice::query(), 'billing.invoice.show', $this->jane)->orderBy('id')->pluck('id')->all();

    expect($ids)->toBe([$this->mine->id, $this->teamMate->id]);
});

test('a tenant grant lists every row of the tenant', function () {
    Rbac::role('admin');
    Rbac::grant('admin', 'billing.*@tenant');
    $this->jane->assignRole('admin');

    $ids = Rbac::constrain(Invoice::query(), 'billing.invoice.show', $this->jane)->orderBy('id')->pluck('id')->all();

    expect($ids)->toBe([$this->mine->id, $this->teamMate->id, $this->olasTeam->id]);
});

test('combining an own and a team grant unions the rows', function () {
    Rbac::role('staff');
    Rbac::grant('staff', 'billing.invoice.show@own');
    Rbac::role('manager');
    Rbac::grant('manager', 'billing.invoice.show@team');
    $this->jane->assignRole('staff');
    $this->jane->assignRole('manager');

    $ids = Rbac::constrain(Invoice::query(), 'billing.invoice.show', $this->jane)->orderBy('id')->pluck('id')->all();

    expect($ids)->toBe([$this->mine->id, $this->teamMate->id]);
});

test('a direct deny removes rows from the result', function () {
    Rbac::role('manager');
    Rbac::grant('manager', 'billing.invoice.show@team');
    $this->jane->assignRole('manager');
    $this->jane->denyPermission('billing.invoice.show@team');

    expect(Rbac::constrain(Invoice::query(), 'billing.invoice.show', $this->jane)->count())->toBe(0);
});

test('a user without any grant sees no rows', function () {
    $nobody = $this->makeUser(['name' => 'Nil'], [60]);

    expect(Rbac::constrain(Invoice::query(), 'billing.invoice.show', $nobody)->count())->toBe(0);
});

test('a global grant returns every row unfiltered', function () {
    Rbac::role('super-admin');
    Rbac::grant('super-admin', '*@global');
    $this->jane->assignRole('super-admin');

    expect(Rbac::constrain(Invoice::query(), 'billing.invoice.show', $this->jane)->count())->toBe(4);
});

test('a scoped deny does not hide rows whose scope column is null', function () {
    $orphan = $this->makeInvoice(['user_id' => null, 'team_id' => null, 'tenant_id' => 1]);
    Rbac::grant('admin', 'billing.invoice.show@tenant');
    $this->jane->assignRole('admin');
    Rbac::deny($this->jane, 'billing.invoice.show@team');
    Rbac::deny($this->jane, 'billing.invoice.show@own');

    $ids = Rbac::constrain(Invoice::query(), 'billing.invoice.show', $this->jane)->pluck('id')->all();

    // The list and the single-record check must agree.
    expect(Rbac::allows('billing.invoice.show', $orphan, $this->jane))->toBeTrue()
        ->and($ids)->toContain($orphan->id)
        ->and($ids)->toContain($this->olasTeam->id)
        ->and($ids)->not->toContain($this->mine->id)
        ->and($ids)->not->toContain($this->teamMate->id);
});
