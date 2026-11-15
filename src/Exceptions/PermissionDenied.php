<?php

namespace Siberfx\RbacPermission\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;

/**
 * Thrown by Rbac::enforce(), the middleware and the console guard. Renders as HTTP 403.
 */
class PermissionDenied extends AuthorizationException
{
    public function __construct(
        public readonly string $permission,
        public readonly string $decision = 'none',
        ?string $message = null,
    ) {
        parent::__construct($message ?? ($decision === 'deny'
            ? "Access to [{$permission}] is explicitly denied."
            : "Missing permission [{$permission}]."));
    }
}
