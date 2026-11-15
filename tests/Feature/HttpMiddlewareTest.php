<?php

use Siberfx\RbacPermission\Facades\Rbac;

beforeEach(function () {
    $this->user = $this->makeUser(['name' => 'Jane'], [10]);
    $this->invoice = $this->makeInvoice(['user_id' => $this->user->id, 'team_id' => 10, 'tenant_id' => 1]);

    Rbac::role('staff');
    Rbac::grant('staff', 'billing.invoice.show@own');
    $this->user->assignRole('staff');
});

test('an attribute on the action drives the check', function () {
    $this->actingAs($this->user)
        ->get("/invoices/{$this->invoice->id}")
        ->assertOk()
        ->assertSee('shown');
});

test('the attribute check denies a permission the user lacks', function () {
    $this->actingAs($this->user)
        ->delete("/invoices/{$this->invoice->id}")
        ->assertForbidden();
});

test('the middleware argument form checks the named route parameter', function () {
    $this->actingAs($this->user)
        ->get("/legacy/{$this->invoice->id}")
        ->assertOk();
});

test('an unauthenticated request is rejected with 401 before any check', function () {
    // JSON request, so the framework returns 401 instead of redirecting to a login route.
    $this->getJson("/invoices/{$this->invoice->id}")
        ->assertUnauthorized();
});
