# Changelog

All notable changes to `siberfx/rbac-permission` are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/).

## [1.5.0] - 2026-11-16

### Added

- Admin screens: Blade pages for roles, role grants and user role assignments, served under `rbac.ui.prefix`. Off by default (`rbac.ui.enabled`).
- Each page checks a catalog permission: `admin.role.show`, `admin.role.edit`, `admin.user.show`, `admin.user.edit`.
- `Rbac::canGrant()`. An actor can only put a permission on a role if they hold every matching permission at that scope or wider. This stops role editors from handing out access they lack.

### Upgrading

- No migration. To use the screens, set `rbac.ui.enabled` to `true`.

## [1.4.0] - 2026-11-16

### Added

- Multiple authentication guards. Roles carry a `guard`, and a slug is unique per guard, so `manager` can exist for `web` and `admin` with different grants.
- `HasRbac::rbacGuard()` says which guard a user model uses (defaults to `auth.defaults.guard`). Roles, ranks and grants are resolved within that guard.
- `rbac.guards` config: roles and ranks for each additional guard. `rbac:sync` writes every guard unless `--guard` is given.
- `--guard` option on `rbac:sync`, `rbac:matrix` and `rbac:grant`.
- Checks without an explicit user use the first logged-in user across the default and configured guards.

### Changed

- `Rbac::role()`, `grant()`, `revoke()`, `sync()` and `matrix()` accept an optional guard. String role names in `assign()` and `unassign()` are resolved in the user's guard.

### Upgrading

- Publish and run the new migration `add_guard_to_rbac_roles`. Existing roles get the default guard. The unique index on `slug` is replaced by one on `(guard, slug)`.

## [1.3.0] - 2026-11-16

### Added

- `Siberfx\RbacPermission\Events\RbacChanged` is dispatched after every change to roles, assignments and direct grants. Actions: `role.granted`, `role.revoked`, `role.assigned`, `role.unassigned`, `permission.allowed`, `permission.denied`, `permission.withdrawn`, `roles.synced`, `grants.pruned`.
- Optional audit log. With `rbac.audit.enabled = true`, each event is stored in `rbac_audit_logs` (actor, subject, permission, scope, effect, expiry, properties).

### Upgrading

- Publish and run the new migration `create_rbac_audit_logs_table` if you plan to enable the audit log. Nothing reads or writes the table while `rbac.audit.enabled` is false.

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
