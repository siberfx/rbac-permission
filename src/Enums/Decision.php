<?php

declare(strict_types=1);

namespace Siberfx\RbacPermission\Enums;

/**
 * The outcome of a permission check. A matching deny always beats an allow;
 * None means no grant matched at all.
 */
enum Decision: string
{
    case Allow = 'allow';
    case Deny = 'deny';
    case None = 'none';

    public function allowed(): bool
    {
        return $this === self::Allow;
    }

    /**
     * True only for an explicit deny. None is not allowed either, but nothing denied it.
     */
    public function denied(): bool
    {
        return $this === self::Deny;
    }
}
