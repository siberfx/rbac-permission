<?php

namespace Siberfx\RbacPermission\Tests\Fixtures;

use Siberfx\RbacPermission\Attributes\RequiresPermission;

class InvoiceController
{
    #[RequiresPermission('billing.invoice.show', subject: 'invoice')]
    public function show(Invoice $invoice): string
    {
        return 'shown';
    }

    #[RequiresPermission('billing.invoice.delete', subject: 'invoice')]
    public function destroy(Invoice $invoice): string
    {
        return 'deleted';
    }
}
