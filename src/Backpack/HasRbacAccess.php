<?php

namespace Siberfx\RbacPermission\Backpack;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Add to a Backpack CRUD controller and call applyRbacAccess() at the end of setup():
 *
 *   class InvoiceCrudController extends CrudController
 *   {
 *       use HasRbacAccess;
 *
 *       protected ?string $rbacResource = 'billing.invoice';
 *
 *       public function setup(): void
 *       {
 *           CRUD::setModel(Invoice::class);
 *           // ...
 *           $this->applyRbacAccess();
 *       }
 *   }
 *
 * The admin user is the Backpack guard's user (backpack_auth()).
 */
trait HasRbacAccess
{
    protected function applyRbacAccess(): void
    {
        if ($this->rbacResource === null || $this->rbacResource === '') {
            throw new LogicException('Set $rbacResource (for example "billing.invoice") before calling applyRbacAccess().');
        }

        $user = backpack_auth()->user();

        Access::apply($this->crud, $this->rbacResource, $user instanceof Model ? $user : null);
    }
}
