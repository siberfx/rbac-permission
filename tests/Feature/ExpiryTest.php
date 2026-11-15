<?php

use Illuminate\Support\Carbon;
use Siberfx\RbacPermission\Facades\Rbac;
use Siberfx\RbacPermission\Models\UserGrant;

beforeEach(function () {
    Rbac::sync(['staff' => ['billing.invoice.show@own']]);
});

test('an expired role assignment grants nothing', function () {
    $user = $this->makeUser(['name' => 'Jane']);
    Rbac::assign($user, 'staff', null, Carbon::now()->subMinute());

    expect(Rbac::allows('billing.invoice.show', null, $user))->toBeFalse()
        ->and($user->refresh()->hasRole('staff'))->toBeFalse()
        ->and(Rbac::rankOf($user))->toBeNull();
});

test('a role assignment applies until its expiry', function () {
    $user = $this->makeUser(['name' => 'Jane']);
    Rbac::assign($user, 'staff', null, Carbon::now()->addDay());

    expect(Rbac::allows('billing.invoice.show', null, $user))->toBeTrue()
        ->and($user->refresh()->hasRole('staff'))->toBeTrue();
});

test('an expired direct allow grants nothing and a future one does', function () {
    $user = $this->makeUser(['name' => 'Jane']);
    $user->allowPermission('billing.refund.approve@global', Carbon::now()->subMinute());

    expect(Rbac::allows('billing.refund.approve', null, $user))->toBeFalse();

    $user->allowPermission('billing.refund.approve@global', Carbon::now()->addDay());

    expect(Rbac::allows('billing.refund.approve', null, $user))->toBeTrue();
});

test('an expired direct deny no longer blocks the grant', function () {
    $user = $this->makeUser(['name' => 'Jane']);
    Rbac::assign($user, 'staff');
    $invoice = $this->makeInvoice(['user_id' => $user->getKey()]);

    $user->denyPermission('billing.invoice.show@own', Carbon::now()->subMinute());
    expect(Rbac::allows('billing.invoice.show', $invoice, $user))->toBeTrue();

    $user->denyPermission('billing.invoice.show@own', Carbon::now()->addDay());
    expect(Rbac::allows('billing.invoice.show', $invoice, $user))->toBeFalse();
});

test('assigning again without an expiry makes the role permanent', function () {
    $user = $this->makeUser(['name' => 'Jane']);
    Rbac::assign($user, 'staff', null, Carbon::now()->subMinute());
    Rbac::assign($user, 'staff');

    expect(Rbac::allows('billing.invoice.show', null, $user))->toBeTrue();
});

test('pruneExpired deletes only the expired rows', function () {
    $user = $this->makeUser(['name' => 'Jane']);
    Rbac::assign($user, 'staff', null, Carbon::now()->subMinute());
    $user->allowPermission('billing.refund.approve@global', Carbon::now()->subMinute());
    $user->allowPermission('billing.invoice.edit@own', Carbon::now()->addDay());

    expect(Rbac::pruneExpired())->toBe(2)
        ->and(UserGrant::query()->count())->toBe(1);
});

test('rbac:assign accepts an --expires option', function () {
    $user = $this->makeUser(['name' => 'Jane']);

    $this->artisan("rbac:assign staff {$user->getKey()} --expires=\"+7 days\"")->assertExitCode(0);

    expect($user->refresh()->roles->first()->pivot->expires_at)->not->toBeNull()
        ->and(Rbac::allows('billing.invoice.show', null, $user))->toBeTrue();
});
