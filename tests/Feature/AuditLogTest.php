<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Siberfx\RbacPermission\Events\RbacChanged;
use Siberfx\RbacPermission\Facades\Rbac;

beforeEach(function () {
    Rbac::sync(['staff' => ['billing.invoice.show@own']]);
});

test('nothing is written to the audit table while audit is disabled', function () {
    config(['rbac.audit.enabled' => false]);
    $user = $this->makeUser(['name' => 'Jane']);

    Rbac::assign($user, 'staff');

    expect(DB::table('rbac_audit_logs')->count())->toBe(0);
});

test('a role assignment is recorded with its subject and role', function () {
    config(['rbac.audit.enabled' => true]);
    $user = $this->makeUser(['name' => 'Jane']);

    Rbac::assign($user, 'staff');

    $row = DB::table('rbac_audit_logs')->where('action', 'role.assigned')->first();

    expect($row)->not->toBeNull()
        ->and($row->subject_type)->toBe($user->getMorphClass())
        ->and($row->subject_id)->toBe((string) $user->getKey())
        ->and(json_decode($row->properties, true))->toMatchArray(['role' => 'staff']);
});

test('the actor passed as "by" is recorded, not the authenticated user', function () {
    config(['rbac.audit.enabled' => true]);
    Rbac::sync(['admin' => ['billing.*@tenant'], 'manager' => ['billing.invoice.show@team']]);

    $admin = $this->makeUser(['name' => 'Admin']);
    $target = $this->makeUser(['name' => 'Target']);
    Rbac::assign($admin, 'admin');
    Rbac::assign($target, 'manager', $admin);

    $row = DB::table('rbac_audit_logs')->where('action', 'role.assigned')->where('subject_id', (string) $target->getKey())->first();

    expect($row->actor_type)->toBe($admin->getMorphClass())
        ->and($row->actor_id)->toBe((string) $admin->getKey());
});

test('direct grants record permission, scope, effect and expiry columns', function () {
    config(['rbac.audit.enabled' => true]);
    $user = $this->makeUser(['name' => 'Jane']);
    $until = now()->addDay();

    $user->denyPermission('billing.invoice.delete@own', $until);

    $row = DB::table('rbac_audit_logs')->where('action', 'permission.denied')->first();

    expect($row->permission)->toBe('billing.invoice.delete')
        ->and($row->scope)->toBe('own')
        ->and($row->effect)->toBe('deny')
        ->and($row->expires_at)->toBe($until->format('Y-m-d H:i:s'));
});

test('a change that removes nothing is not recorded', function () {
    config(['rbac.audit.enabled' => true]);
    $user = $this->makeUser(['name' => 'Jane']);

    Rbac::unassign($user, 'staff');
    Rbac::withdraw($user, 'billing.invoice.show@own');

    expect(DB::table('rbac_audit_logs')->count())->toBe(0);
});

test('sync records one roles.synced entry', function () {
    config(['rbac.audit.enabled' => true]);

    Rbac::sync(['manager' => ['billing.invoice.show@team']]);

    expect(DB::table('rbac_audit_logs')->where('action', 'roles.synced')->count())->toBe(1);
});

test('every change dispatches RbacChanged, even when audit is off', function () {
    config(['rbac.audit.enabled' => false]);
    Event::fake([RbacChanged::class]);
    $user = $this->makeUser(['name' => 'Jane']);

    $user->allowPermission('billing.refund.approve@global');

    Event::assertDispatched(RbacChanged::class, fn (RbacChanged $event) => $event->action === 'permission.allowed'
        && $event->subject?->is($user)
        && $event->details['permission'] === 'billing.refund.approve');
});
