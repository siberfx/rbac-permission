<?php

namespace Siberfx\RbacPermission\Events;

use Illuminate\Database\Eloquent\Model;

/**
 * Dispatched after every change to roles, assignments and direct grants.
 *
 * Actions: role.granted, role.revoked, role.assigned, role.unassigned,
 * permission.allowed, permission.denied, permission.withdrawn,
 * roles.synced, grants.pruned.
 *
 * Listen to it for your own reporting, or enable rbac.audit to store it.
 */
final class RbacChanged
{
    /**
     * @param  array<string, mixed>  $details  e.g. permission, scope, effect, role, expires_at
     */
    public function __construct(
        public readonly string $action,
        public readonly ?Model $actor,
        public readonly ?Model $subject,
        public readonly array $details = [],
    ) {}
}
