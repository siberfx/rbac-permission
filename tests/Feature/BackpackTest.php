<?php

use Backpack\CRUD\app\Library\CrudPanel\CrudPanel;
use Siberfx\RbacPermission\Backpack\Access;
use Siberfx\RbacPermission\Backpack\HasRbacAccess;
use Siberfx\RbacPermission\Facades\Rbac;
use Siberfx\RbacPermission\Tests\Fixtures\BackpackUser;
use Siberfx\RbacPermission\Tests\Fixtures\Invoice;

beforeEach(function () {
    Rbac::sync(config('rbac.roles'));
});

function backpackPanel(): CrudPanel
{
    $crud = new CrudPanel;
    $crud->setModel(Invoice::class);

    return $crud;
}

test('the list shows only the rows the user may reach', function () {
    $staff = $this->makeUser(['name' => 'Staff']);
    Rbac::assign($staff, 'staff');   // billing.invoice.show@own
    $mine = $this->makeInvoice(['user_id' => $staff->getKey()]);
    $this->makeInvoice(['user_id' => 999]);

    $crud = backpackPanel();
    Access::apply($crud, 'billing.invoice', $staff);

    expect($crud->query->pluck('id')->all())->toBe([$mine->getKey()]);
});

test('a user with no role sees an empty list', function () {
    $nobody = $this->makeUser(['name' => 'Nobody']);
    $this->makeInvoice(['user_id' => $nobody->getKey()]);

    $crud = backpackPanel();
    Access::apply($crud, 'billing.invoice', $nobody);

    expect($crud->query->count())->toBe(0);
});

test('edit is decided per record by scope', function () {
    $staff = $this->makeUser(['name' => 'Staff']);
    Rbac::assign($staff, 'staff');   // billing.invoice.edit@own
    $mine = $this->makeInvoice(['user_id' => $staff->getKey()]);
    $other = $this->makeInvoice(['user_id' => 999]);

    $crud = backpackPanel();
    Access::apply($crud, 'billing.invoice', $staff);

    expect($crud->hasAccess('update', $mine))->toBeTrue()
        ->and($crud->hasAccess('update', $other))->toBeFalse();
});

test('create is decided without a record and delete is denied to staff', function () {
    $staff = $this->makeUser(['name' => 'Staff']);
    Rbac::assign($staff, 'staff');
    $mine = $this->makeInvoice(['user_id' => $staff->getKey()]);

    $crud = backpackPanel();
    Access::apply($crud, 'billing.invoice', $staff);

    expect($crud->hasAccess('create'))->toBeTrue()
        ->and($crud->hasAccess('delete', $mine))->toBeFalse();
});

test('an access rule already set on the panel still applies', function () {
    $admin = $this->makeUser(['name' => 'Admin']);
    Rbac::assign($admin, 'admin');
    $invoice = $this->makeInvoice();

    $crud = backpackPanel();
    $crud->denyAccess('update');
    Access::apply($crud, 'billing.invoice', $admin);

    expect($crud->hasAccess('update', $invoice))->toBeFalse()
        ->and($crud->hasAccess('show', $invoice))->toBeTrue();
});

test('the trait applies the rules to the Backpack guard user', function () {
    $controller = new class
    {
        use HasRbacAccess;

        public CrudPanel $crud;

        protected ?string $rbacResource = 'billing.invoice';

        public function setupRules(): void
        {
            $this->applyRbacAccess();
        }
    };

    Rbac::sync(['viewer' => ['billing.invoice.show@global', 'billing.invoice.edit@global']], false, 'backpack');
    $admin = BackpackUser::create(['name' => 'Ops']);
    $admin->assignRole('viewer');
    $this->makeInvoice(['user_id' => 1]);
    $this->makeInvoice(['user_id' => 2]);

    $this->actingAs($admin, 'backpack');
    $controller->crud = backpackPanel();
    $controller->setupRules();

    expect($controller->crud->query->count())->toBe(2)
        ->and($controller->crud->hasAccess('update', Invoice::first()))->toBeTrue();
});

test('the trait refuses to run without a resource', function () {
    $controller = new class
    {
        use HasRbacAccess;

        public CrudPanel $crud;

        protected ?string $rbacResource = null;

        public function setupRules(): void
        {
            $this->applyRbacAccess();
        }
    };

    $controller->crud = backpackPanel();

    expect(fn () => $controller->setupRules())->toThrow(LogicException::class);
});

test('the Backpack user keeps its roles in the Backpack guard', function () {
    $admin = BackpackUser::create(['name' => 'Ops']);

    expect($admin->rbacGuard())->toBe('backpack');

    Rbac::sync(['viewer' => ['billing.invoice.show@global']], false, 'backpack');
    $admin->assignRole('viewer');

    expect($admin->hasRole('viewer'))->toBeTrue()
        ->and(Rbac::allows('billing.invoice.show', null, $admin))->toBeTrue();
});
