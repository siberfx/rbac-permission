<?php

namespace Siberfx\RbacPermission\Attributes;

use Attribute;

/**
 * Declare the permission a controller action, a class or a console command needs.
 *
 *   #[RequiresPermission('billing.invoice.show', subject: 'invoice')]
 *   public function show(Invoice $invoice) { ... }
 *
 *   #[RequiresPermission('ops.cache.run')]
 *   class ClearCache extends Command { ... }
 *
 * On a controller, `subject` names the route parameter that holds the record.
 * Each attribute is checked, so a class-level and a method-level attribute both apply.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class RequiresPermission
{
    public function __construct(
        public readonly string $permission,
        public readonly ?string $subject = null,
    ) {}
}
