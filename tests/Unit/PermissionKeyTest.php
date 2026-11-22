<?php

declare(strict_types=1);

use Siberfx\RbacPermission\Exceptions\InvalidPermission;
use Siberfx\RbacPermission\Support\PermissionKey;

test('a grant with a scope is parsed into pattern and scope', function () {
    expect(PermissionKey::parse('billing.invoice.edit@team', 'own'))
        ->toBe(['billing.invoice.edit', 'team']);
});

test('a grant without a scope falls back to the default scope', function () {
    expect(PermissionKey::parse('billing.invoice.edit', 'own'))
        ->toBe(['billing.invoice.edit', 'own']);
});

test('malformed grants are rejected', function (string $input) {
    PermissionKey::parse($input, 'own');
})->with([
    'too many segments' => ['a.b.c.d'],
    'empty segment' => ['billing..edit'],
    'upper case' => ['Billing.invoice.edit'],
    'empty scope' => ['billing.invoice.edit@'],
    'bad scope' => ['billing.invoice.edit@Team Lead'],
])->throws(InvalidPermission::class);

test('a trailing wildcard covers the rest of the key', function () {
    expect(PermissionKey::matches('billing.*', 'billing.invoice.edit'))->toBeTrue()
        ->and(PermissionKey::matches('billing.invoice.*', 'billing.invoice.edit'))->toBeTrue()
        ->and(PermissionKey::matches('*', 'anything.at.all'))->toBeTrue();
});

test('a middle wildcard matches exactly one segment', function () {
    expect(PermissionKey::matches('*.invoice.edit', 'billing.invoice.edit'))->toBeTrue()
        ->and(PermissionKey::matches('*.invoice.edit', 'billing.refund.edit'))->toBeFalse();
});

test('an exact pattern never matches a longer or shorter key', function () {
    expect(PermissionKey::matches('billing.invoice', 'billing.invoice.edit'))->toBeFalse()
        ->and(PermissionKey::matches('billing.invoice.edit', 'billing.invoice'))->toBeFalse()
        ->and(PermissionKey::matches('billing.invoice.edit', 'billing.invoice.show'))->toBeFalse();
});
