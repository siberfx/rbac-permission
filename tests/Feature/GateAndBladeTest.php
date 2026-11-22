<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Siberfx\RbacPermission\Facades\Rbac;

beforeEach(function () {
    $this->user = $this->makeUser(['name' => 'Jane'], [10]);
    $this->invoice = $this->makeInvoice(['user_id' => $this->user->id, 'team_id' => 10, 'tenant_id' => 1]);

    Rbac::role('staff');
    Rbac::grant('staff', 'billing.invoice.edit@own');
    $this->user->assignRole('staff');
});

test('$user->can() works for catalog permissions through the Gate', function () {
    expect($this->user->can('billing.invoice.edit', $this->invoice))->toBeTrue()
        ->and($this->user->can('billing.invoice.delete', $this->invoice))->toBeFalse();
});

test('Gate::allows() and @can style checks see the same decision', function () {
    expect(Gate::forUser($this->user)->allows('billing.invoice.edit', $this->invoice))->toBeTrue();
});

test('names outside the catalog are left to other gates', function () {
    Gate::define('reports.export-legacy', fn () => true);

    expect($this->user->can('reports.export-legacy'))->toBeTrue();
});

test('an explicit deny stops other gates from granting access', function () {
    Gate::define('billing.invoice.edit', fn () => true);
    $this->user->denyPermission('billing.invoice.edit@own');

    expect($this->user->can('billing.invoice.edit', $this->invoice))->toBeFalse();
});

test('the @rbac Blade directive renders the else branch when denied', function () {
    $this->actingAs($this->user);

    // Blade::if generates @rbac / @else / @endrbac. Markers sit on their own
    // line: Blade's directive regex ignores an "@" glued to a word character.
    $template = "@rbac('%s', \$invoice)\nyes\n@else\nno\n@endrbac";

    $allowed = Blade::render(sprintf($template, 'billing.invoice.edit'), ['invoice' => $this->invoice]);
    $denied = Blade::render(sprintf($template, 'billing.invoice.delete'), ['invoice' => $this->invoice]);

    expect(trim($allowed))->toBe('yes')
        ->and(trim($denied))->toBe('no');
});
