<?php

namespace Siberfx\RbacPermission\Concerns;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Siberfx\RbacPermission\Models\Role;
use Siberfx\RbacPermission\Rbac;

/**
 * Add to your user model. Gives roles, direct overrides and, through Gate, $user->can().
 */
trait HasRbac
{
    public function roles(): MorphToMany
    {
        return $this->morphToMany(Role::class, 'model', config('rbac.tables.role_assignments'), 'model_id', 'role_id')
            ->withPivot('expires_at');
    }

    /**
     * True when the user holds the role and it has not expired.
     */
    public function hasRole(string $slug): bool
    {
        $pivot = (string) config('rbac.tables.role_assignments');

        return $this->roles()->where('slug', $slug)
            ->where(fn ($q) => $q->whereNull("{$pivot}.expires_at")->orWhere("{$pivot}.expires_at", '>', now()))
            ->exists();
    }

    /**
     * Pass $by to hold the change to the role hierarchy (see rbac.ranks).
     * Pass $expiresAt to make the role time-boxed.
     */
    public function assignRole(Role|string $role, ?Model $by = null, ?DateTimeInterface $expiresAt = null): void
    {
        app(Rbac::class)->assign($this, $role, $by, $expiresAt);
    }

    public function removeRole(Role|string $role, ?Model $by = null): int
    {
        return app(Rbac::class)->unassign($this, $role, $by);
    }

    public function allowPermission(string $permission, ?DateTimeInterface $expiresAt = null): void
    {
        app(Rbac::class)->allow($this, $permission, $expiresAt);
    }

    public function denyPermission(string $permission, ?DateTimeInterface $expiresAt = null): void
    {
        app(Rbac::class)->deny($this, $permission, $expiresAt);
    }

    public function withdrawPermission(string $permission): int
    {
        return app(Rbac::class)->withdraw($this, $permission);
    }

    /**
     * Team ids used by the "team" scope. Override, or point rbac.user.teams_relation at your relation.
     *
     * @return list<int|string>
     */
    public function rbacTeamIds(): array
    {
        $relation = (string) config('rbac.user.teams_relation', 'teams');

        if (! method_exists($this, $relation)) {
            return [];
        }

        return $this->{$relation}->modelKeys();
    }
}
