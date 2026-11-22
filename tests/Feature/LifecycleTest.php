<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Siberfx\RbacPermission\Facades\Rbac;
use Siberfx\RbacPermission\Rbac as RbacService;

test('memoised grants do not outlive a request or a queued job', function () {
    // The per-request memo only: a write that bypasses the Rbac API is not seen by the cross-request cache.
    config(['rbac.cache.ttl' => 0]);
    $user = $this->makeUser(['name' => 'Jane']);

    expect(Rbac::allows('billing.invoice.show', null, $user))->toBeFalse();

    // Another process grants access without touching this instance.
    DB::table(config('rbac.tables.user_grants'))->insert([
        'model_type' => $user->getMorphClass(), 'model_id' => (string) $user->getKey(),
        'permission' => 'billing.invoice.show', 'scope' => 'own', 'effect' => 'allow',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    // Octane and the queue worker do this between requests and jobs.
    app()->forgetScopedInstances();
    Rbac::clearResolvedInstances();

    expect(Rbac::allows('billing.invoice.show', null, $user))->toBeTrue();
});

test('the service is shared within one lifecycle', function () {
    expect(app(RbacService::class))->toBe(app(RbacService::class));
});

test('the grant cache is invalidated only once the transaction commits', function () {
    config(['rbac.cache.ttl' => 3600, 'rbac.cache.store' => 'array']);
    $user = $this->makeUser(['name' => 'Jane']);
    Rbac::allows('billing.invoice.show', null, $user);   // fills the cache
    $version = fn () => cache()->store('array')->get('rbac.version');
    $before = $version();

    DB::transaction(function () use ($before, $version) {
        Rbac::grant('staff', 'billing.invoice.show@own');

        // Another request could still read the old rows here; it must not cache them under a new version.
        expect($version())->toBe($before);
    });

    expect($version())->not->toBe($before);
});

test('a rolled back change leaves the grant cache alone', function () {
    config(['rbac.cache.ttl' => 3600, 'rbac.cache.store' => 'array']);
    $user = $this->makeUser(['name' => 'Jane']);
    Rbac::allows('billing.invoice.show', null, $user);
    $before = cache()->store('array')->get('rbac.version');

    try {
        DB::transaction(function () {
            Rbac::grant('staff', 'billing.invoice.show@own');
            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
    }

    expect(cache()->store('array')->get('rbac.version'))->toBe($before);
});
