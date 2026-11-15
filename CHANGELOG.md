# Changelog

All notable changes to `siberfx/rbac-permission` are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/).

## [1.2.0] - 2026-11-16

### Added

- Time-boxed grants. `assign()`, `assignRole()`, `allow()`, `deny()`, `allowPermission()` and `denyPermission()` accept an optional expiry. Expired role assignments and direct grants stop applying immediately.
- `Rbac::pruneExpired()` deletes expired rows.
- `rbac:assign --expires=` (for example `"+7 days"`).
- `HasRbac::hasRole()` ignores expired assignments, and `roles()` exposes the `expires_at` pivot.

### Changed

- Re-assigning a role or re-issuing a direct grant updates its expiry. Omitting the date makes it permanent again.

### Upgrading

- Publish and run the new migration `add_expires_at_to_rbac_grants` (adds `expires_at` to `rbac_role_assignments` and `rbac_user_grants`).

## [1.1.0] - 2026-11-16

### Added

- Role hierarchy. Roles carry a `rank` (set in `rbac.ranks`, applied by `rbac:sync`). An actor may only assign or remove a role strictly below their highest rank.
- `Rbac::assign()`, `Rbac::unassign()`, `HasRbac::assignRole()` and `removeRole()` accept an optional actor (`by:`). Without one nothing is checked, as before.
- `Rbac::rankOf()` and `Rbac::canAssign()`.
- `Siberfx\RbacPermission\Exceptions\RoleAssignmentDenied`, raised when the hierarchy blocks a change.

### Upgrading

- Publish and run the new migration `add_rank_to_rbac_roles` (adds `rank` to `rbac_roles`, default `0`).

## [1.0.0] - 2026-11-15

First stable release.

### Added

- Permission grammar `module.resource.verb@scope` with segment wildcards (`*`, `billing.*`, `*@global`).
- Scope ladder `own` < `team` < `tenant` < `global`, configurable and extendable with custom scope resolvers.
- Role and direct user grants, with deny overrides that always win over allows.
- Query constraints: `Rbac::constrain()` narrows an Eloquent query to the rows the user may reach.
- Enforcement points for models (`HasRbac`, `HasRbacScopes`), Gate (`$user->can()`, `@can`), Blade (`@rbac`), HTTP (`rbac` middleware, `#[RequiresPermission]` on controller actions), services (`Rbac::enforce()`), and console commands (`#[RequiresPermission]` on commands).
- Explainable decisions with `Rbac::explain()` and `rbac:explain`.
- Roles as code in `config/rbac.php`, synced with `rbac:sync` in one transaction.
- Commands: `rbac:sync`, `rbac:matrix`, `rbac:explain`, `rbac:assign`, `rbac:grant`.
- Optional cross-request grant cache with automatic invalidation.
- Test suite written with Pest, run in CI against PHP 8.4 and 8.5 and Laravel 12 and 13.
