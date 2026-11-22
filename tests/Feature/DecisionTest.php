<?php

declare(strict_types=1);

use Siberfx\RbacPermission\Enums\Decision;
use Siberfx\RbacPermission\Facades\Rbac;

beforeEach(function () {
    $this->user = $this->makeUser(['name' => 'Jane']);
});

test('decision() returns the enum and decide() its string value', function () {
    expect(Rbac::decision('billing.invoice.show', null, $this->user))->toBe(Decision::None)
        ->and(Rbac::decide('billing.invoice.show', null, $this->user))->toBe('none');

    Rbac::allow($this->user, 'billing.invoice.show@own');

    expect(Rbac::decision('billing.invoice.show', null, $this->user))->toBe(Decision::Allow)
        ->and(Rbac::decide('billing.invoice.show', null, $this->user))->toBe(Decision::Allow->value);

    Rbac::deny($this->user, 'billing.invoice.show@global');

    expect(Rbac::decision('billing.invoice.show', null, $this->user))->toBe(Decision::Deny);
});

test('the enum answers the common questions', function () {
    expect(Decision::Allow->allowed())->toBeTrue()
        ->and(Decision::Deny->allowed())->toBeFalse()
        ->and(Decision::None->allowed())->toBeFalse()
        ->and(Decision::Deny->denied())->toBeTrue()
        ->and(Decision::None->denied())->toBeFalse()
        ->and(Decision::from('none'))->toBe(Decision::None);
});

test('the string constants match the enum values', function () {
    expect(Siberfx\RbacPermission\Rbac::ALLOW)->toBe(Decision::Allow->value)
        ->and(Siberfx\RbacPermission\Rbac::DENY)->toBe(Decision::Deny->value)
        ->and(Siberfx\RbacPermission\Rbac::NONE)->toBe(Decision::None->value);
});
