<?php

declare(strict_types=1);

namespace Siberfx\RbacPermission\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;

/**
 * Thrown when an actor tries to assign or remove a role that is not strictly below their own rank.
 */
class RoleAssignmentDenied extends AuthorizationException
{
    public function __construct(
        public readonly string $role,
        public readonly ?int $actorRank = null,
    ) {
        parent::__construct($actorRank === null
            ? "You hold no role rank, so you may not manage role [{$role}]."
            : "Role [{$role}] is not below your rank [{$actorRank}].");
    }
}
